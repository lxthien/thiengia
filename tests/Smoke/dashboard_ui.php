<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['admin/layout.html.twig' => '{% block main %}{% endblock %}']),
    new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2) . '/templates'),
]);
$twig = new Twig\Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
$twig->addFunction(new Twig\TwigFunction('asset', fn ($path) => $path));
$twig->addFunction(new Twig\TwigFunction('is_granted', fn ($role) => false));
$twig->addFunction(new Twig\TwigFunction('path', fn ($route, $params = []) => '/' . $route));
$data = [
 'totalPosts' => 0, 'totalViews' => 0, 'totalComments' => 0, 'approvedComments' => 0, 'pendingComments' => 0,
 'recentPosts' => [], 'topPosts' => [], 'viewTrends' => ['2026-09-20' => 0, '2026-09-21' => 0],
];
$html = $twig->render('admin/dashboard/index.html.twig', $data);
if (str_contains($html, 'class="dash-chart"') || !str_contains($html, 'Chưa ghi nhận lượt xem')) {
 throw new RuntimeException('Empty chart state failed');
}
foreach (['/admin_contact_index', '/admin_settings_global', '/admin_page_new', '/admin_activity_log_index'] as $restrictedLink) {
 if (str_contains($html, $restrictedLink)) { throw new RuntimeException('Restricted action exposed'); }
}
$data['viewTrends'] = ['2026-09-19' => 0, '2026-09-20' => 10, '2026-09-21' => 20];
$html = $twig->render('admin/dashboard/index.html.twig', $data);
if (substr_count($html, 'class="dash-chart-column"') !== 3 || !str_contains($html, 'height:50%') || !str_contains($html, 'height:100%') || !str_contains($html, '30 lượt xem')) {
 throw new RuntimeException('Chart values failed');
}
echo "PASS: dashboard empty/data chart branches and restricted action visibility.\n";
