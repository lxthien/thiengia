<?php
// Integration checks use a process-local SQLite database, never the configured CMS database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
putenv('DATABASE_URL=sqlite:///:memory:');
$kernel = new class('dev', true) extends App\Kernel {
    public function getCacheDir(): string { return sys_get_temp_dir() . '/thiengia-image-smoke-' . substr(sha1(__FILE__), 0, 12); }
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void {
        parent::build($container);
        $container->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $container): void {
                $container->getDefinition('form.factory')->setPublic(true);
                $container->getDefinition('twig')->setPublic(true);
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

$csrf = new Symfony\Component\Security\Csrf\CsrfTokenManager();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.csrf.token_manager', $csrf);
$services->set('twig', $c->get('twig'));
$services->setParameter('kernel.project_dir', dirname(__DIR__, 2));
$services->set('parameter_bag', new Symfony\Component\DependencyInjection\ParameterBag\ContainerBag($services));
$log = new class extends App\Service\ActivityLogService {
    public function __construct() {}
    public function log($action, $entityType, $entityId = null, $entityTitle = null, $details = null) {}
};
$controller = new App\Controller\Admin\GalleryAlbumController($em, $log, new App\Service\GalleryAlbumOrderValidator(), new Psr\Log\NullLogger(), new App\Service\GalleryImageOrderValidator(), new App\Service\GalleryImageInput());
$controller->setContainer($services);
$album = (new App\Entity\GalleryAlbum())->setName('Test')->setEnable(true);
$other = (new App\Entity\GalleryAlbum())->setName('Other');
$em->persist($album); $em->persist($other); $em->flush();
$token = $csrf->getToken('gallery_images_' . $album->getId())->getValue();
function req(array $data): Symfony\Component\HttpFoundation\Request {
    return Symfony\Component\HttpFoundation\Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE'=>'application/json'], json_encode($data));
}
$public = dirname(__DIR__, 2) . '/public';
$input = new App\Service\GalleryImageInput();
foreach (['/uploads/media/../outside.jpg', '/uploads/media/%2e%2e/outside.jpg', '/uploads/media/%5c.jpg', 'https://example.com/a.jpg', '/uploads/media/missing.jpg'] as $url) {
    try { $input->imageUrl($url, $public); throw new RuntimeException('Unsafe URL accepted'); }
    catch (InvalidArgumentException $expected) {}
}
foreach ([['caption'=>[]], ['alt'=>str_repeat('a',256)]] as $bad) {
    try { $input->metadata($bad); throw new RuntimeException('Bad metadata accepted'); }
    catch (InvalidArgumentException $expected) {}
}
// Read an existing media file for the URL only; this test never writes image files.
$mediaPath = null;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public . '/uploads/media', FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile() || str_contains(str_replace('\\','/',$file->getPathname()), '/thumbs/')) continue;
    $url = substr(str_replace('\\','/',$file->getPathname()), strlen(str_replace('\\','/',$public)));
    try { $mediaPath = $input->imageUrl($url, $public); break; } catch (InvalidArgumentException $ignored) {}
}
check($mediaPath !== null, 'An existing media image is required for integration testing');
$badCsrf = $controller->addImageAction(req(['token'=>'bad','imageUrl'=>$mediaPath]), $album);
check($badCsrf->getStatusCode() === 403, 'Add requires CSRF');
$added = $controller->addImageAction(req(['token'=>$token,'imageUrl'=>$mediaPath]), $album);
check($added->getStatusCode() === 200, $added->getContent());
$data = json_decode($added->getContent(), true);
check(isset($data['html']) && str_contains($data['html'], 'data-image-form'), 'New image returns rendered row');
$image = $em->find(App\Entity\GalleryImage::class, $data['image']['id']);
check($controller->addImageAction(req(['token'=>$token,'imageUrl'=>$mediaPath]), $album)->getStatusCode() === 409, 'Duplicate rejected');
$updated = $controller->updateImageAction(req(['token'=>$token,'caption'=>'<b>Caption</b>','alt'=>'Alt','expected'=>['caption'=>'','alt'=>'']]), $image);
check($updated->getStatusCode() === 200, $updated->getContent());
check($image->getAlt() === 'Alt', 'Metadata persisted');
check($controller->updateImageAction(req(['token'=>$token,'caption'=>'overwrite','expected'=>['caption'=>'','alt'=>'']]), $image)->getStatusCode() === 409, 'Stale metadata rejected');
$otherToken = $csrf->getToken('gallery_images_' . $other->getId())->getValue();
check($controller->deleteImageAction(req(['token'=>$otherToken]), $image)->getStatusCode() === 403, 'Cross-album token rejected');
$second = (new App\Entity\GalleryImage())->setAlbum($album)->setImageUrl($mediaPath)->setPosition(1);
$em->persist($second); $em->flush();
$current = [$image->getId(), $second->getId()];
check($controller->reorderImagesAction(req(['token'=>$token,'items'=>array_reverse($current),'expected'=>$current]),$album)->getStatusCode() === 200,'Valid full reorder');
check($controller->reorderImagesAction(req(['token'=>$token,'items'=>$current,'expected'=>$current]),$album)->getStatusCode() === 409,'Stale order rejected');
check($controller->reorderImagesAction(req(['token'=>$token,'items'=>[$image->getId()],'expected'=>array_reverse($current)]),$album)->getStatusCode() === 422,'Partial order rejected');
check($controller->deleteImageAction(req(['token'=>$token]),$image)->getStatusCode() === 200,'Image deletion succeeds');
check(is_file($public . rawurldecode($mediaPath)), 'Original media file preserved');
check($em->getRepository(App\Entity\GalleryImage::class)->count([]) === 1, 'Other image preserved');
echo "PASS isolated SQLite image API: CSRF, paths, metadata, create, duplicate, stale edits, order and delete.\n";
$kernel->shutdown();
