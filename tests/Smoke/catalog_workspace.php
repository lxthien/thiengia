<?php
// Integration checks use a process-local SQLite database, never the configured CMS database.
// Bao phủ entity Service + Project và màn hình quản lý dùng chung của chúng.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
putenv('DATABASE_URL=sqlite:///:memory:');
$kernel = new class('dev', true) extends App\Kernel {
    public function getCacheDir(): string { return sys_get_temp_dir() . '/thiengia-catalog-smoke-' . substr(sha1(__FILE__), 0, 12); }
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

use App\Entity\GalleryAlbum;
use App\Entity\GalleryImage;
use App\Entity\HomepageSection;
use App\Entity\Project;
use App\Entity\Service;
use App\Enum\SectionType;
use App\Form\ProjectType;
use App\Form\ServiceType;
use App\Service\Homepage\ServicesSectionResolver;
use App\Service\Homepage\WorksSectionResolver;

(new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
    $em->getClassMetadata(Service::class),
    $em->getClassMetadata(Project::class),
    $em->getClassMetadata(GalleryAlbum::class),
    $em->getClassMetadata(GalleryImage::class),
]);
$factory = $c->get('form.factory');
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$serviceData = [
    'name' => 'Xây nhà trọn gói',
    'image' => '/uploads/media/svc-1.jpg',
    'description' => 'Chìa khóa trao tay từ thiết kế đến hoàn thiện.',
    'priceFrom' => 'từ 7,5 triệu/m²',
    'featuredOnHome' => '1',
    'enable' => '1',
];
$projectData = [
    'name' => 'Biệt thự anh Minh',
    'spec' => 'Đất 10×20m · 3 tầng',
    'coverImage' => '/uploads/media/work-1.jpg',
    'linkUrl' => '/cong-trinh/biet-thu-anh-minh',
    'featuredOnHome' => '1',
    'enable' => '1',
];

// --- Dịch vụ: form hợp lệ và các trường hợp phải bị từ chối ---
$service = new Service();
$form = $factory->create(ServiceType::class, $service, ['csrf_protection' => false]);
$form->submit($serviceData);
check($form->isValid(), (string) $form->getErrors(true));
check($service->getPriceFrom() === 'từ 7,5 triệu/m²', 'Đơn giá phải được lưu');
check($service->getFeaturedOnHome(), 'Cờ đưa lên trang chủ phải bật');

foreach ([['name', ''], ['name', str_repeat('a', 121)], ['description', ''], ['description', str_repeat('a', 401)]] as [$field, $value]) {
    $invalid = $factory->create(ServiceType::class, new Service(), ['csrf_protection' => false]);
    $invalid->submit(array_replace($serviceData, [$field => $value]));
    check(!$invalid->isValid(), 'Dịch vụ sai ở ' . $field . ' mà vẫn được chấp nhận');
}

// Ô trống phải thành NULL chứ không phải chuỗi rỗng.
$blank = new Service();
$blankForm = $factory->create(ServiceType::class, $blank, ['csrf_protection' => false]);
$blankForm->submit(array_replace($serviceData, ['priceFrom' => '   ', 'image' => '', 'slug' => '']));
check($blankForm->isValid(), (string) $blankForm->getErrors(true));
check($blank->getPriceFrom() === null && $blank->getImage() === null && $blank->getSlug() === null, 'Ô trống phải là NULL');

// --- Công trình: ảnh đại diện là bắt buộc ---
$project = new Project();
$form = $factory->create(ProjectType::class, $project, ['csrf_protection' => false]);
$form->submit($projectData);
check($form->isValid(), (string) $form->getErrors(true));

foreach ([['name', ''], ['spec', ''], ['coverImage', '']] as [$field, $value]) {
    $invalid = $factory->create(ProjectType::class, new Project(), ['csrf_protection' => false]);
    $invalid->submit(array_replace($projectData, [$field => $value]));
    check(!$invalid->isValid(), 'Công trình thiếu ' . $field . ' mà vẫn được chấp nhận');
}

// --- Lưu và lọc theo trạng thái ---
$em->persist($service);
$em->persist($project);

$hidden = (new Service())->setName('Dịch vụ đã ẩn')->setDescription('x')->setEnable(false)->setPosition(1);
$notFeatured = (new Service())->setName('Thiết kế kiến trúc')->setDescription('x')->setFeaturedOnHome(false)->setPosition(2);
$em->persist($hidden);
$em->persist($notFeatured);

$hiddenProject = (new Project())->setName('Công trình đã ẩn')->setSpec('x')->setCoverImage('/uploads/media/x.jpg')->setEnable(false)->setPosition(1);
$offHome = (new Project())->setName('Không lên trang chủ')->setSpec('x')->setCoverImage('/uploads/media/y.jpg')->setFeaturedOnHome(false)->setPosition(2);
$em->persist($hiddenProject);
$em->persist($offHome);
$em->flush();
$em->clear();

$serviceRepo = $em->getRepository(Service::class);
$projectRepo = $em->getRepository(Project::class);

check(count($serviceRepo->findAllOrdered()) === 3, 'Phải có 3 dịch vụ');
check(count($serviceRepo->findActiveOrdered()) === 2, 'Dịch vụ đã ẩn không được trả về');
check(count($serviceRepo->findFeatured()) === 1, 'Chỉ dịch vụ nổi bật mới lên trang chủ');

// Dịch vụ không lên trang chủ VẪN phải nằm trong ô "Nhu cầu" của form báo giá.
$names = $serviceRepo->findActiveNames();
check(in_array('Thiết kế kiến trúc', $names, true), 'Dịch vụ ngoài trang chủ vẫn phải có trong form báo giá');
check(!in_array('Dịch vụ đã ẩn', $names, true), 'Dịch vụ đã ẩn không được vào form báo giá');

check(count($projectRepo->findActiveOrdered()) === 2, 'Công trình đã ẩn không được trả về');
check(count($projectRepo->findFeatured()) === 1, 'Chỉ công trình nổi bật mới lên trang chủ');
check(count($projectRepo->findFeatured(1)) === 1, 'Giới hạn số công trình phải có tác dụng');

// --- Resolver nạp đúng dữ liệu cho khối ---
$servicesResolver = new ServicesSectionResolver($serviceRepo);
$worksResolver = new WorksSectionResolver($projectRepo);
check($servicesResolver->supports(SectionType::Services) && !$servicesResolver->supports(SectionType::Works), 'Resolver dịch vụ nhận sai loại khối');
check($worksResolver->supports(SectionType::Works) && !$worksResolver->supports(SectionType::Services), 'Resolver công trình nhận sai loại khối');

$servicesSection = new HomepageSection(SectionType::Services);
check(count($servicesResolver->resolve($servicesSection)['services']) === 1, 'Khối dịch vụ phải lấy đúng dịch vụ nổi bật');
$worksSection = new HomepageSection(SectionType::Works);
check(count($worksResolver->resolve($worksSection)['projects']) === 1, 'Khối công trình phải lấy đúng công trình nổi bật');

// --- Sắp xếp: thứ tự lưu được và tra cứu lại đúng ---
$em->beginTransaction();
$rows = $serviceRepo->findForReorder();
$current = array_map(fn ($r) => $r->getId(), $rows);
$next = array_reverse($current);
$positions = array_flip($next);
foreach ($rows as $row) { $row->setPosition($positions[$row->getId()]); }
$em->flush();
$em->commit();
$em->clear();
check(array_map(fn ($r) => $r->getId(), $em->getRepository(Service::class)->findAllOrdered()) === $next, 'Thứ tự dịch vụ phải được lưu');

// --- Xóa công trình không kéo theo album ---
$album = (new GalleryAlbum())->setName('Album công trình');
$em->persist($album);
$linked = (new Project())->setName('Có album')->setSpec('x')->setCoverImage('/uploads/media/z.jpg')->setAlbum($album)->setPosition(9);
$em->persist($linked);
$em->flush();
$linkedId = $linked->getId();
$albumId = $album->getId();
$em->remove($linked);
$em->flush();
$em->clear();
check($em->getRepository(Project::class)->find($linkedId) === null, 'Công trình phải bị xóa');
check($em->getRepository(GalleryAlbum::class)->find($albumId) !== null, 'Xóa công trình không được xóa album');

echo "PASS isolated SQLite: service/project validation, blank-to-NULL, featured vs enabled filtering, quote-form choices, resolver dispatch, reorder persistence and album detachment.\n";
$kernel->shutdown();
