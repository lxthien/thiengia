<?php
// Integration checks use a process-local SQLite database, never the configured CMS database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
putenv('DATABASE_URL=sqlite:///:memory:');
$kernel = new class('dev', true) extends App\Kernel {
    public function getCacheDir(): string { return sys_get_temp_dir() . '/thiengia-banner-smoke-' . substr(sha1(__FILE__), 0, 12); }
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
    $em->getClassMetadata(App\Entity\BannerCategory::class),
    $em->getClassMetadata(App\Entity\Banner::class),
]);
$factory = $c->get('form.factory');
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$group = new App\Entity\BannerCategory();
$form = $factory->create(App\Form\BannerCategoryType::class, $group, ['csrf_protection' => false]);
$form->submit(['name' => 'Nhóm kiểm thử', 'url' => '', 'zone' => 'hero']);
check($form->isValid(), (string) $form->getErrors(true));
check($group->getUrl() === 'nhom-kiem-thu', 'Vietnamese slug generation');
$em->persist($group); $em->flush();
$duplicate = $factory->create(App\Form\BannerCategoryType::class, new App\Entity\BannerCategory(), ['csrf_protection' => false]);
$duplicate->submit(['name'=>'Duplicate', 'url'=>'nhom-kiem-thu', 'zone'=>'hero']);
check(!$duplicate->isValid(), 'Duplicate group code rejected');
$banner = new App\Entity\Banner();
$form = $factory->create(App\Form\BannerType::class, $banner, ['csrf_protection' => false]);
$form->submit(['name'=>'Test banner', 'bannercategory'=>(string)$group->getId(), 'url'=>'/test', 'urlImage'=>'/uploads/media/test.jpg', 'enable'=>'1']);
check($form->isValid(), (string) $form->getErrors(true));
$banner->setPosition(0); $em->persist($banner); $em->flush();
check($em->getRepository(App\Entity\Banner::class)->count(['bannercategory'=>$group]) === 1, 'Group ownership');
check(count($em->getRepository(App\Entity\Banner::class)->findActiveByZone('hero')) === 1, 'Public zone query');
$banner->setEnable(false); $em->flush();
check(count($em->getRepository(App\Entity\Banner::class)->findActiveByZone('hero')) === 0, 'Hidden banner excluded');
$em->beginTransaction();
check(count($em->getRepository(App\Entity\Banner::class)->findForZone('hero', true)) === 1, 'Locked order query');
$em->rollback();
$invalid = $factory->create(App\Form\BannerType::class, new App\Entity\Banner(), ['csrf_protection'=>false]);
$invalid->submit(['name'=>'', 'url'=>'javascript:alert(1)', 'urlImage'=>'']);
check(!$invalid->isValid(), 'Missing required fields and unsafe links rejected');
$group2 = (new App\Entity\BannerCategory())->setName('Second')->setUrl('second')->setZone('hero');
$em->persist($group2); $em->flush();
$banner->setBannerCategory($group2); $em->flush();
check($em->getRepository(App\Entity\Banner::class)->count(['bannercategory'=>$group]) === 0, 'Move banner to another group');
$em->remove($group); $em->flush();
$em->remove($banner); $em->flush();
echo "PASS in-memory SQLite: category slug/unique validation, banner form/persistence, visibility, zone query, lock, move and delete.\n";
$kernel->shutdown();
