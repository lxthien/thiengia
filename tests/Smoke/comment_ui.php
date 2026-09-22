<?php
// Isolated rendering regression test: does not connect to or modify the database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['admin/layout.html.twig' => '{% block main %}{% endblock %}']),
    new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2) . '/templates'),
]);
$twig = new Twig\Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
$twig->addFunction(new Twig\TwigFunction('asset', fn ($path) => $path));
$twig->addFunction(new Twig\TwigFunction('path', fn ($route, $params = []) => '/' . $route . '?' . http_build_query($params)));
$twig->addFunction(new Twig\TwigFunction('csrf_token', fn ($id) => 'test-token-' . $id));
$twig->addFilter(new Twig\TwigFilter('trans', fn ($message) => $message));
$twig->addGlobal('app', ['flashes' => ['success' => ['Đã duyệt bình luận.']]]);
$twig->addFunction(new Twig\TwigFunction('knp_pagination_render', fn () => 'pagination-test'));
$pagination = new class extends ArrayObject {
    public int $currentPageNumber = 1;
    public int $totalItemCount = 0;
    public int $pageCount = 1;
};
$data = ['pagination' => $pagination, 'filters' => ['q' => '', 'status' => ''], 'counts' => ['all' => 0, 'pending' => 0, 'approved' => 0]];
$check = function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$html = $twig->render('admin/comment/index.html.twig', $data);
$check(str_contains($html, 'Chưa có bình luận'), 'Missing empty library state');
$check(str_contains($html, 'comment-notice'), 'Missing inline flash message');
$check(!str_contains($html, 'class="content mt-3"'), 'Legacy floating flash wrapper returned');
$data['filters']['q'] = 'no-results';
$html = $twig->render('admin/comment/index.html.twig', $data);
$check(str_contains($html, 'Không có bình luận phù hợp'), 'Missing filtered empty state');
$row = [
    'id' => 1, 'author' => '<script>alert(1)</script>', 'approved' => false,
    'createdAt' => new DateTimeImmutable('2026-09-22 08:00:00'), 'email' => 'test@example.test',
    'phone' => '0123456789', 'ip' => '127.0.0.1',
    'news' => ['id' => 9, 'isPage' => false, 'title' => 'Test article'],
    'parent' => null, 'content' => '<img src=x onerror=alert(1)>' . str_repeat('Long comment ', 50),
];
$pagination->append($row);
$reply = $row;
$reply['id'] = 2;
$reply['approved'] = true;
$reply['news']['isPage'] = true;
$reply['parent'] = ['id' => 1, 'author' => 'Parent'];
$reply['content'] = 'Short reply';
$pagination->append($reply);
$pagination->totalItemCount = 2;
$data['counts'] = ['all' => 2, 'pending' => 1, 'approved' => 1];
$data['filters'] = ['q' => 'test', 'status' => 'pending'];
$html = $twig->render('admin/comment/index.html.twig', $data);
$check(!str_contains($html, '<script>') && !str_contains($html, '<img src=x'), 'Unsafe comment output');
$check(str_contains($html, '&lt;script&gt;'), 'Author not escaped');
foreach (['admin_page_edit', 'admin_news_edit', 'admin_comment_approve', 'admin_comment_unapprove', 'Đọc toàn bộ bình luận', 'data-comment-delete', 'test-token-comment_status_1', 'test-token-bulk_comment', 'Các trả lời thuộc bình luận'] as $expected) {
    $check(str_contains($html, $expected), 'Missing expected UI: ' . $expected);
}
$check(substr_count($html, 'id="comment-confirm"') === 1, 'Confirmation dialog must be shared');
$check(substr_count($html, 'class="comment-row comment-row--reply"') === 1, 'Only replies should be indented');
$check(str_contains($html, 'class="comment-row" id="comment-1"'), 'Root comment should not be indented');
$check(str_contains($html, 'admin_comment_delete?q=test&amp;status=pending&amp;page=1&amp;id=1'), 'Delete must retain filters');
$pagination->pageCount = 2;
$html = $twig->render('admin/comment/index.html.twig', $data);
$check(str_contains($html, 'pagination-test'), 'Missing pagination');
echo "PASS: comment empty/filter states, safe text, post/page links, moderation actions, CSRF markup and pagination.\n";
