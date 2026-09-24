<?php
// Isolated rendering regression test: does not connect to or modify the database.
// Màn quản lý Dịch vụ / Công trình dùng chung khung _reorder_manager.html.twig.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Entity\Project;
use App\Entity\Service;

$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader([
        'admin/layout.html.twig' => '{% block stylesheets %}{% endblock %}{% block main %}{% endblock %}{% block javascripts %}{% endblock %}',
    ]),
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

$withImage = (new Service())->setName('Xây nhà trọn gói')
    ->setDescription('Chìa khóa trao tay từ thiết kế đến hoàn thiện.')
    ->setPriceFrom('từ 7,5 triệu/m²')
    ->setImage('/uploads/media/svc-1.jpg')
    ->setPosition(0);
$withoutImage = (new Service())->setName('Thiết kế kiến trúc')
    ->setDescription('Chỉ nhận yêu cầu qua form báo giá.')
    ->setFeaturedOnHome(false)
    ->setEnable(false)
    ->setPosition(1);

$html = $twig->render('admin/service/index.html.twig', ['objects' => [$withImage, $withoutImage]]);

$check(str_contains($html, 'Xây nhà trọn gói'), 'Thiếu tên dịch vụ');
$check(str_contains($html, 'từ 7,5 triệu/m²'), 'Thiếu đơn giá');
$check(str_contains($html, '/uploads/media/svc-1.jpg'), 'Thiếu ảnh đại diện');
$check(str_contains($html, 'Chưa có ảnh'), 'Dịch vụ không ảnh phải hiện ô trống thay vì ảnh hỏng');
$check(str_contains($html, '1 đang hiển thị · 1 đã ẩn'), 'Thiếu tổng kết trạng thái');
$check(str_contains($html, 'data-reorder-url="/admin_service_reorder?"'), 'Thiếu endpoint sắp xếp');
$check(str_contains($html, 'test-token-reorder_services'), 'Thiếu CSRF token sắp xếp');
$check(substr_count($html, 'data-manager-visibility') === 2, 'Thiếu nút bật/tắt');
$check(str_contains($html, 'Thêm dịch vụ'), 'Thiếu nút thêm mới');

// Xóa là quyền ROLE_ADMIN, và phải kèm CSRF token riêng cho từng bản ghi.
$check(str_contains($html, 'test-token-delete_service_'), 'Nút xóa phải có CSRF token');
$granted = false;
$restricted = $twig->render('admin/service/index.html.twig', ['objects' => [$withImage, $withoutImage]]);
$check(!str_contains($restricted, 'admin_service_delete'), 'Editor không được thấy nút xóa');
$check(str_contains($restricted, 'data-manager-visibility'), 'Editor vẫn bật/tắt được dịch vụ');

$granted = true;
$empty = $twig->render('admin/service/index.html.twig', ['objects' => []]);
$check(str_contains($empty, 'Chưa có dịch vụ nào'), 'Thiếu trạng thái rỗng');
$check(str_contains($empty, 'app:homepage:import-content'), 'Trạng thái rỗng phải chỉ ra lệnh nhập nội dung');

$project = (new Project())->setName('Biệt thự anh Minh')
    ->setSpec('Đất 10×20m · 3 tầng')
    ->setCoverImage('/uploads/media/work-1.jpg')
    ->setLocation('Thảo Điền')
    ->setFeaturedOnHome(false)
    ->setPosition(0);

$projects = $twig->render('admin/project/index.html.twig', ['objects' => [$project]]);
$check(str_contains($projects, 'Biệt thự anh Minh'), 'Thiếu tên công trình');
$check(str_contains($projects, 'Đất 10×20m · 3 tầng'), 'Thiếu dòng thông số');
$check(str_contains($projects, 'Thảo Điền'), 'Thiếu địa điểm');
$check(str_contains($projects, 'Không lên trang chủ'), 'Phải báo rõ công trình không hiện ở trang chủ');
$check(str_contains($projects, 'data-reorder-url="/admin_project_reorder?"'), 'Thiếu endpoint sắp xếp công trình');

echo "PASS: catalog rows, missing-image fallback, status summary, reorder wiring, role-gated delete, featured badge and empty states.\n";
