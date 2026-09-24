<?php
// Isolated rendering regression test: does not connect to or modify the database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Entity\HomepageSection;
use App\Enum\SectionType;

$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['admin/layout.html.twig' => '{% block main %}{% endblock %}']),
    new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2) . '/templates'),
]);
$twig = new Twig\Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
$twig->addFunction(new Twig\TwigFunction('asset', fn ($path) => $path));
$twig->addFunction(new Twig\TwigFunction('path', fn ($route, $params = []) => '/' . $route . '?' . http_build_query($params)));
$twig->addFunction(new Twig\TwigFunction('csrf_token', fn ($id) => 'test-token-' . $id));
$twig->addFilter(new Twig\TwigFilter('trans', fn ($message) => $message));

$granted = true;
$twig->addFunction(new Twig\TwigFunction('is_granted', function ($role) use (&$granted) { return $granted; }));

$check = function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};

$edited = new HomepageSection(SectionType::Commitments);
$edited->setLabel('Vì sao chọn Thiện Gia')->setTitle('6 cam kết <em>bằng văn bản</em>')->setPosition(0);

$untouched = new HomepageSection(SectionType::Figures);
$untouched->setPosition(1)->setEnable(false);

$html = $twig->render('admin/homepage_section/_manager.html.twig', ['objects' => [$edited, $untouched]]);

// Khối đã sửa: hiện nội dung admin nhập, không để lọt thẻ HTML ra màn hình danh sách.
$check(str_contains($html, 'Vì sao chọn Thiện Gia'), 'Thiếu chữ nhỏ đã nhập');
$check(str_contains($html, '6 cam kết bằng văn bản'), 'Thiếu tiêu đề đã nhập');
$check(!str_contains($html, '<em>bằng văn bản</em>'), 'Danh sách phải strip thẻ, không render HTML của người nhập');

// Khối chưa sửa: nói rõ đang dùng nội dung mặc định thay vì hiện ô trống.
$check(str_contains($html, 'Đang dùng nội dung mặc định'), 'Thiếu trạng thái dùng nội dung mặc định');
$check(str_contains($html, SectionType::Figures->hint()), 'Thiếu gợi ý nguồn dữ liệu của khối');
$check(str_contains($html, 'Mã khối: figures'), 'Thiếu mã khối để đối chiếu với template');

// Trạng thái hiển thị.
$check(str_contains($html, 'Đang hiển thị') && str_contains($html, 'Đã ẩn'), 'Thiếu badge trạng thái');
$check(str_contains($html, '1 đang hiển thị · 1 đã ẩn'), 'Thiếu tổng kết số khối đang bật/tắt');

// Hạ tầng kéo-thả.
$check(str_contains($html, 'data-reorder-url="/admin_homepage_section_reorder?"'), 'Thiếu endpoint sắp xếp');
$check(str_contains($html, 'test-token-reorder_homepage_sections'), 'Thiếu CSRF token sắp xếp');
$check(substr_count($html, 'class="dd-item banner-row"') === 2, 'Thiếu hàng kéo-thả');
$check(substr_count($html, 'data-manager-move="up"') === 2, 'Thiếu nút di chuyển cho thao tác bàn phím');

// Chỉ ROLE_ADMIN mới thấy nút bật/tắt.
$check(substr_count($html, 'data-manager-visibility') === 2, 'ROLE_ADMIN phải thấy nút bật/tắt');
$granted = false;
$restricted = $twig->render('admin/homepage_section/_manager.html.twig', ['objects' => [$edited, $untouched]]);
$check(!str_contains($restricted, 'data-manager-visibility'), 'Editor không được thấy nút bật/tắt');
$check(str_contains($restricted, 'Sửa nội dung'), 'Editor vẫn phải sửa được nội dung');

// Danh sách rỗng: chỉ đúng câu lệnh seed cần chạy.
$granted = true;
$empty = $twig->render('admin/homepage_section/_manager.html.twig', ['objects' => []]);
$check(str_contains($empty, 'Chưa có khối trang chủ nào'), 'Thiếu trạng thái rỗng');
$check(str_contains($empty, 'app:homepage:seed-sections'), 'Trạng thái rỗng phải chỉ ra lệnh seed');

echo "PASS: section copy escaping, default-content state, source hints, visibility badges, reorder wiring, role-gated toggles and empty state.\n";
