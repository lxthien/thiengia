<?php
// Integration checks use a process-local SQLite database, never the configured CMS database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
putenv('DATABASE_URL=sqlite:///:memory:');
$kernel = new class('dev', true) extends App\Kernel {
    public function getCacheDir(): string { return sys_get_temp_dir() . '/thiengia-homepage-section-smoke-' . substr(sha1(__FILE__), 0, 12); }
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

use App\Entity\HomepageSection;
use App\Entity\NewsCategory;
use App\Enum\SectionType;
use App\Form\HomepageSectionType;
use App\Service\Homepage\HomepageDataProvider;
use App\Service\Homepage\SectionIcons;
use App\Service\Homepage\SectionResolverInterface;
use App\Service\PositionOrderValidator;

// NewsCategory cần cho ô chọn danh mục của khối Cẩm nang.
(new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
    $em->getClassMetadata(HomepageSection::class),
    $em->getClassMetadata(NewsCategory::class),
]);
$factory = $c->get('form.factory');
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$formFor = static fn (SectionType $type, ?HomepageSection $section = null) => $factory->create(
    HomepageSectionType::class,
    $section ?? new HomepageSection($type),
    ['csrf_protection' => false]
);

// --- Sắp xếp: payload hợp lệ và các payload phải bị từ chối ---
$order = new PositionOrderValidator();
$order->validate([2, 1], [1, 2], [1, 2], 'khối');
$order->validate([], [], [], 'khối');
foreach ([
    [[1, 1], [1, 2], InvalidArgumentException::class],
    [['1', 2], [1, 2], InvalidArgumentException::class],
    [[1], [1, 2], InvalidArgumentException::class],
    [[1, 99], [1, 2], InvalidArgumentException::class],
    [null, [1, 2], InvalidArgumentException::class],
    [[2, 1], [2, 1], DomainException::class],
] as [$items, $expected, $exception]) {
    try { $order->validate($items, $expected, [1, 2], 'khối'); throw new RuntimeException('Invalid order accepted'); }
    catch (Throwable $error) { if (get_class($error) !== $exception) throw $error; }
}

// --- Form chỉ hiện đúng những ô mà loại khối thật sự dùng ---
$hero = $formFor(SectionType::Hero);
check($hero->has('label') && $hero->has('title') && $hero->has('subtitle'), 'Hero thiếu ô chữ');
check($hero->has('points') && !$hero->has('limit'), 'Hero phải có gạch đầu dòng, không có ô số bài viết');
$figures = $formFor(SectionType::Figures);
check(!$figures->has('label') && !$figures->has('title') && !$figures->has('subtitle'), 'Dải số liệu không có tiêu đề nên không được hiện ô chữ');
check($figures->has('items') && $figures->has('enable'), 'Dải số liệu phải có danh sách con số và ô bật/tắt');
$news = $formFor(SectionType::News);
check($news->has('limit') && !$news->has('items'), 'Khối cẩm nang chỉ cấu hình số bài');
$cta = $formFor(SectionType::Cta);
check(!$cta->has('label') && $cta->has('title') && $cta->has('subtitle') && $cta->has('image'), 'CTA cần tiêu đề, ghi chú và ảnh nền');
$about = $formFor(SectionType::About);
check($about->has('image') && $about->has('imageAlt') && $about->has('checks'), 'Giới thiệu cần ảnh, alt và gạch đầu dòng');
$gallery = $formFor(SectionType::Gallery);
check(!$gallery->has('items') && !$gallery->has('image'), 'Thư viện lấy dữ liệu từ entity nên không có ô nội dung');

// --- Ô để trống phải thành NULL để template dùng nội dung mặc định ---
$blank = new HomepageSection(SectionType::Commitments);
$form = $formFor(SectionType::Commitments, $blank);
$form->submit(['label' => '   ', 'title' => '', 'enable' => '1']);
check($form->isValid(), (string) $form->getErrors(true));
check($blank->getLabel() === null && $blank->getTitle() === null, 'Ô trống phải là NULL, không phải chuỗi rỗng');
check($blank->getConfig() === [], 'Danh sách rỗng không được sinh khóa thừa trong config');

// --- Tiêu đề quá dài và số bài viết ngoài khoảng bị chặn ---
$tooLong = $formFor(SectionType::Services);
$tooLong->submit(['title' => str_repeat('a', 256), 'enable' => '1']);
check(!$tooLong->isValid(), 'Tiêu đề 256 ký tự phải bị từ chối');
$badLimit = $formFor(SectionType::News);
$badLimit->submit(['limit' => '99', 'enable' => '1']);
check(!$badLimit->isValid(), 'Số bài viết 99 phải bị từ chối');

// --- Nội dung lặp: form ghi thẳng vào config ---
$figuresSection = new HomepageSection(SectionType::Figures);
$form = $formFor(SectionType::Figures, $figuresSection);
$form->submit(['items' => [
    ['value' => '10', 'unit' => '+', 'label' => 'Năm kinh nghiệm'],
    ['value' => '500', 'unit' => '+', 'label' => 'Công trình bàn giao'],
], 'enable' => '1']);
check($form->isValid(), (string) $form->getErrors(true));
$items = $figuresSection->getConfigValue('items');
check(count($items) === 2 && $items[0]['value'] === 10 && $items[1]['label'] === 'Công trình bàn giao', 'Con số phải được lưu vào config');

$figuresForm = $formFor(SectionType::Figures, $figuresSection);
$figuresForm->submit(['items' => [], 'enable' => '1']);
check(!array_key_exists('items', $figuresSection->getConfig()), 'Xóa hết dòng phải bỏ khóa khỏi config để quay về mặc định theme');

$heroSection = new HomepageSection(SectionType::Hero);
$form = $formFor(SectionType::Hero, $heroSection);
$form->submit(['points' => ['Không phát sinh', '', 'Bảo hành 10 năm'], 'enable' => '1']);
check($form->isValid(), (string) $form->getErrors(true));
check($heroSection->getConfigValue('points') === ['Không phát sinh', 'Bảo hành 10 năm'], 'Dòng trống phải bị loại và đánh lại chỉ số');

$aboutSection = new HomepageSection(SectionType::About);
$form = $formFor(SectionType::About, $aboutSection);
$form->submit([
    'image' => '/uploads/media/about.jpg',
    'imageAlt' => 'Biệt thự sân vườn',
    'checks' => [['title' => 'Đội ngũ', 'text' => 'có chứng chỉ hành nghề']],
    'enable' => '1',
]);
check($form->isValid(), (string) $form->getErrors(true));
check($aboutSection->getConfigValue('image') === '/uploads/media/about.jpg', 'Ảnh phải được lưu vào config');
check($aboutSection->getConfigValue('checks')[0]['title'] === 'Đội ngũ', 'Gạch đầu dòng phải được lưu vào config');

// --- Khối Cẩm nang: chọn danh mục thay cho JSON gõ tay trong Cài đặt chung ---
$newsSection = new HomepageSection(SectionType::News);
$form = $formFor(SectionType::News, $newsSection);
check($form->has('categories'), 'Khối cẩm nang phải có ô chọn danh mục');
$form->submit(['limit' => '6', 'enable' => '1']);
check($form->isValid(), (string) $form->getErrors(true));
check($newsSection->getConfigValue('limit') === 6, 'Số bài viết phải được lưu vào config');
check(!array_key_exists('categories', $newsSection->getConfig()), 'Không chọn danh mục thì không sinh khóa thừa');
$badCategory = $formFor(SectionType::News);
$badCategory->submit(['categories' => ['9999'], 'enable' => '1']);
check(!$badCategory->isValid(), 'Danh mục không tồn tại phải bị từ chối');

// --- Icon cam kết là danh sách chọn, không nhận markup tự do ---
$badIcon = $formFor(SectionType::Commitments);
$badIcon->submit(['items' => [['name' => 'Bảo hành', 'desc' => '10 năm', 'icon' => '<script>alert(1)</script>']], 'enable' => '1']);
check(!$badIcon->isValid(), 'Icon ngoài danh sách phải bị từ chối');
check(SectionIcons::markup('khong-ton-tai') === SectionIcons::markup(SectionIcons::DEFAULT), 'Icon lạ phải rơi về icon mặc định');
check(str_contains(SectionIcons::markup('shield'), '<path'), 'Icon phải trả về markup SVG');

// --- Lọc HTML ở tầng lưu: giữ <em>, bỏ script và thẻ khối ---
$dirty = new HomepageSection(SectionType::Works);
$dirty->setTitle('<script>alert(1)</script>500 mái ấm <em>đã bàn giao</em><div>x</div>');
$dirty->setPosition(0);
$em->persist($dirty);
$em->flush();
$em->clear();
$repo = $em->getRepository(HomepageSection::class);
$saved = $repo->findOneByType(SectionType::Works);
check(str_contains($saved->getTitle(), '<em>đã bàn giao</em>'), 'Phải giữ <em> trong tiêu đề');
check(!str_contains($saved->getTitle(), '<script') && !str_contains($saved->getTitle(), '<div'), 'Phải lọc script và thẻ khối: ' . $saved->getTitle());

$saved->setTitle('<script>x</script>Đổi tiêu đề');
$em->flush();
$em->clear();
check(!str_contains($repo->findOneByType(SectionType::Works)->getTitle(), '<script'), 'preUpdate cũng phải lọc HTML');

// --- Config đi trọn vòng qua DB ---
$stored = $repo->findOneByType(SectionType::Works);
$stored->setConfig(['items' => [['name' => 'Nhà phố', 'spec' => '4 tầng']]]);
$em->flush();
$em->clear();
check($repo->findOneByType(SectionType::Works)->getConfigValue('items')[0]['spec'] === '4 tầng', 'Config phải lưu và đọc lại được từ DB');

// --- Thứ tự + bật/tắt ---
foreach ([SectionType::Hero, SectionType::Figures, SectionType::Cta] as $index => $type) {
    $section = new HomepageSection($type);
    $section->setPosition($index + 1)->setEnable($type !== SectionType::Figures);
    $em->persist($section);
}
$em->flush();
$em->clear();
check(count($repo->findAllOrdered()) === 4, 'Phải có 4 khối');
check(count($repo->findActiveOrdered()) === 3, 'Khối đã ẩn không được trả về');

$em->beginTransaction();
$rows = $repo->findForReorder();
$current = array_map(fn ($r) => $r->getId(), $rows);
$next = array_reverse($current);
$order->validate($next, $current, $current, 'khối');
$positions = array_flip($next);
foreach ($rows as $row) { $row->setPosition($positions[$row->getId()]); }
$em->flush();
$em->commit();
$em->clear();
check(array_map(fn ($r) => $r->getId(), $repo->findAllOrdered()) === $next, 'Thứ tự mới phải được lưu');

// --- Provider: đúng thứ tự, bỏ khối ẩn, gọi đúng resolver theo loại ---
$probe = new class implements SectionResolverInterface {
    public function supports(SectionType $type): bool { return $type === SectionType::Cta; }
    public function resolve(HomepageSection $section): array { return ['probe' => 'ok']; }
};
$provider = new HomepageDataProvider($repo, [$probe]);
$visible = $provider->getSections();
check(count($visible) === 3, 'Provider phải bỏ khối đã ẩn');
check(
    array_map(fn ($i) => $i['section']->getType()->value, $visible) === array_map(fn ($r) => $r->getType()->value, $repo->findActiveOrdered()),
    'Provider phải giữ đúng thứ tự position'
);
foreach ($visible as $item) {
    $expected = $item['section']->getType() === SectionType::Cta ? ['probe' => 'ok'] : [];
    check($item['data'] === $expected, 'Resolver chỉ được chạy cho loại khối nó nhận');
}
check(count($provider->getSections(true)) === 4, 'Chế độ xem trước phải gồm cả khối đã ẩn');

// --- Giao diện nhập liệu: danh sách dòng và ô chọn ảnh ---
$twig = $c->get('twig');
$figuresView = $formFor(SectionType::Figures)->createView();
$collection = $twig->render('admin/homepage_section/_collection.html.twig', [
    'field' => $figuresView['items'],
    'add_label' => 'Thêm dòng',
]);
check(str_contains($collection, 'data-prototype'), 'Thiếu prototype để JS thêm dòng');
check(str_contains($collection, '__name__'), 'Prototype phải giữ placeholder __name__');
check(str_contains($collection, 'data-collection-add'), 'Thiếu nút thêm dòng');
check(str_contains($collection, 'Chưa có dòng nào'), 'Thiếu trạng thái rỗng của danh sách');

$picker = $twig->render('admin/components/_image_picker.html.twig', [
    'field' => $formFor(SectionType::Cta)->createView()['image'],
    'picker_id' => 'sectionPicker',
]);
check(str_contains($picker, 'id="sectionPicker_url_input"'), 'Widget ảnh phải mang đúng id mà admin.js ghi vào');
check(str_contains($picker, 'data-media-picker-block'), 'Thiếu wrapper để admin.js khởi tạo picker');
check(str_contains($picker, 'id="sectionPickerModal"'), 'Thiếu modal thư viện ảnh');

// --- Mỗi loại khối phải có đúng 1 file template ---
foreach (SectionType::cases() as $type) {
    $path = dirname(__DIR__, 2) . '/templates/homepage/sections/_' . $type->value . '.html.twig';
    check(is_file($path), 'Thiếu template cho khối ' . $type->value);
}
check(count(SectionType::defaultOrder()) === count(SectionType::cases()), 'defaultOrder phải liệt kê đủ mọi loại khối');

echo "PASS isolated SQLite: per-type fields, config round-trip, blank rows dropped, icon whitelist, length limits, HTML purifying, visibility filtering, reorder persistence, provider dispatch, collection/picker rendering and template coverage.\n";
$kernel->shutdown();
