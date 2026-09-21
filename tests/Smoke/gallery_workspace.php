<?php
// Integration checks use a process-local SQLite database, never the configured CMS database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
putenv('DATABASE_URL=sqlite:///:memory:');
$kernel = new class('dev', true) extends App\Kernel {
    public function getCacheDir(): string { return sys_get_temp_dir() . '/thiengia-gallery-smoke-' . substr(sha1(__FILE__), 0, 12); }
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void {
        parent::build($container);
        $container->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $container): void {
                $container->getDefinition('form.factory')->setPublic(true);
            }
        });
    }
};
$kernel->boot();
$c = $kernel->getContainer();
$em = $c->get('doctrine')->getManager();
if ($em->getConnection()->getParams()['driver'] !== 'pdo_sqlite' || !($em->getConnection()->getParams()['memory'] ?? false)) {
    throw new RuntimeException('Refusing to test outside in-memory SQLite.');
}
(new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
    $em->getClassMetadata(App\Entity\GalleryAlbum::class),
    $em->getClassMetadata(App\Entity\GalleryImage::class),
]);
$factory = $c->get('form.factory');
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$order = new App\Service\GalleryAlbumOrderValidator();
$order->validate([2, 1], [1, 2], [1, 2]);
$order->validate([], [], []);
foreach ([
    [[1, 1], [1, 2], InvalidArgumentException::class],
    [['1', 2], [1, 2], InvalidArgumentException::class],
    [[1], [1, 2], InvalidArgumentException::class],
    [[1, 99], [1, 2], InvalidArgumentException::class],
    [null, [1, 2], InvalidArgumentException::class],
    [[2, 1], [2, 1], LogicException::class],
    [[1 => 1, 2 => 2], [1, 2], InvalidArgumentException::class],
] as [$items, $expected, $exception]) {
    try { $order->validate($items, $expected, [1, 2]); throw new RuntimeException('Invalid order accepted'); }
    catch (Throwable $error) { if (get_class($error) !== $exception) throw $error; }
}
$album = new App\Entity\GalleryAlbum();
$form = $factory->create(App\Form\GalleryAlbumType::class, $album, ['csrf_protection' => false]);
$form->submit(['name'=>'Album kiểm thử', 'description'=>'Mô tả', 'enable'=>'1']);
check($form->isValid(), (string) $form->getErrors(true));
$album->setPosition(1);
$image = (new App\Entity\GalleryImage())->setImageUrl('/uploads/media/test.jpg')->setPosition(1);
$first = (new App\Entity\GalleryImage())->setImageUrl('/uploads/media/first.jpg')->setPosition(0);
$album->addImage($image)->addImage($first);
$empty = (new App\Entity\GalleryAlbum())->setName('Album trống')->setEnable(false)->setPosition(0);
$em->persist($album); $em->persist($empty); $em->flush();
$id = $album->getId(); $em->clear();
$all = $em->getRepository(App\Entity\GalleryAlbum::class)->findAllOrdered();
check(count($all) === 2 && $all[0]->getName() === 'Album trống', 'Albums ordered by position');
check($all[1]->getImages()->isInitialized(), 'Images eagerly loaded');
check($all[1]->getCoverImage()->getImageUrl() === '/uploads/media/first.jpg', 'First ordered image is cover');
check(count($em->getRepository(App\Entity\GalleryAlbum::class)->findActiveOrdered()) === 1, 'Hidden albums excluded');
$em->beginTransaction();
$locked = $em->getRepository(App\Entity\GalleryAlbum::class)->findForReorder();
check(count($locked) === 2, 'Locked complete collection');
$order->validate([$locked[1]->getId(), $locked[0]->getId()], array_map(fn ($a) => $a->getId(), $locked), array_map(fn ($a) => $a->getId(), $locked));
$locked[1]->setPosition(0); $locked[0]->setPosition(1);
$em->flush(); $em->commit(); $em->clear();
check($em->getRepository(App\Entity\GalleryAlbum::class)->findAllOrdered()[0]->getId() === $id, 'Reorder persisted');
foreach (['', str_repeat('a', 256)] as $name) {
    $invalid = $factory->create(App\Form\GalleryAlbumType::class, new App\Entity\GalleryAlbum(), ['csrf_protection'=>false]);
    $invalid->submit(['name'=>$name, 'description'=>'']);
    check(!$invalid->isValid(), 'Invalid name rejected');
}
$album = $em->find(App\Entity\GalleryAlbum::class, $id);
$album->setName('Updated')->setEnable(false); $em->flush();
check(count($em->getRepository(App\Entity\GalleryAlbum::class)->findActiveOrdered()) === 0, 'Visibility persisted');
$em->remove($album); $em->flush();
check($em->getRepository(App\Entity\GalleryImage::class)->count([]) === 0, 'Album deletion cascades image records');
check($em->getRepository(App\Entity\GalleryAlbum::class)->count([]) === 1, 'Other album preserved');
echo "PASS isolated SQLite: forms, eager cover ordering, visibility, full order validation/persistence and cascade deletion.\n";
$kernel->shutdown();
