<?php
// Isolated Twig test. No database access or contact mutations.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$twig = new Twig\Environment(new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['admin/layout.html.twig' => '{% block main %}{% endblock %}']),
    new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2) . '/templates'),
]), ['strict_variables' => true, 'autoescape' => 'html']);
$twig->addFunction(new Twig\TwigFunction('path', fn ($route, $params = []) => '/' . $route . '?' . http_build_query($params)));
$twig->addFunction(new Twig\TwigFunction('csrf_token', fn ($id) => 'test-' . $id));
$twig->addFunction(new Twig\TwigFunction('asset', fn ($path) => $path));
$twig->addFunction(new Twig\TwigFunction('knp_pagination_render', fn () => 'pagination-test'));
$twig->addFilter(new Twig\TwigFilter('trans', fn ($message) => $message));
$twig->addGlobal('app', ['flashes' => ['success' => ['Đã đánh dấu liên hệ là đã đọc.']]]);
$pagination = new class extends ArrayObject {
    public int $currentPageNumber = 1;
    public int $totalItemCount = 0;
    public int $pageCount = 1;
};
$data = ['pagination' => $pagination, 'filters' => ['q'=>'','status'=>''], 'counts'=>['all'=>0,'read'=>0,'unread'=>0]];
$check = function ($ok, $message) { if (!$ok) { throw new RuntimeException($message); } };
$html = $twig->render('admin/contact/index.html.twig', $data);
$check(str_contains($html, 'Chưa có liên hệ'), 'Missing empty state');
$data['filters']['q'] = 'test';
$html = $twig->render('admin/contact/index.html.twig', $data);
$check(str_contains($html, 'Không có liên hệ phù hợp'), 'Missing filtered empty state');
$pagination->append(['id'=>1,'name'=>'<script>alert(1)</script>','isRead'=>false,'phone'=>'0901234567','email'=>'test@example.test','title'=>null,'contents'=>str_repeat('Long message ',60), 'createdAt'=>new DateTimeImmutable()]);
$pagination->append(['id'=>2,'name'=>'Read contact','isRead'=>true,'phone'=>'','email'=>null,'title'=>'Question','contents'=>'<img src=x onerror=alert(1)>Short text', 'createdAt'=>new DateTimeImmutable()]);
$pagination->totalItemCount = 2;
$pagination->pageCount = 2;
$html = $twig->render('admin/contact/index.html.twig', $data);
foreach (['Đánh dấu đã đọc','Đánh dấu chưa đọc','Xem toàn bộ nội dung','Chưa cung cấp','test-contact_read_1','pagination-test','mailto:test@example.test','tel:0901234567'] as $expected) {
    $check(str_contains($html, $expected), 'Missing: ' . $expected);
}
$check(!str_contains($html, '<script>') && !str_contains($html, '<img src=x'), 'Unsafe output');
$check(str_contains($html, 'admin_contact_delete?q=test&amp;status=&amp;page=1&amp;id=1'), 'Lost filter context');
$check(substr_count($html, 'id="contact-confirm"') === 1, 'Duplicate confirmation dialog');
echo "PASS contact empty/filter states, read states, safe text, contact links, CSRF and pagination.\n";
