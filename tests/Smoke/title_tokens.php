<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Entity\News;
use App\Service\TitleTokenResolver;
use App\Twig\TitleTokenExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

$resolver = new TitleTokenResolver('Asia/Ho_Chi_Minh');
$check = static function ($actual, $expected): void {
    if ($actual !== $expected) {
        throw new RuntimeException(var_export([$actual, $expected], true));
    }
};
$before = new DateTimeImmutable('2026-12-31T16:59:59Z');
$after = new DateTimeImmutable('2026-12-31T17:00:00Z');
$check($resolver->resolve('[YEAR] / [YEAR]', $before), '2026 / 2026');
$check($resolver->resolve('[YEAR]', $after), '2027');
$check($resolver->resolve(null, $after), '');
$check($resolver->resolve('[year] [MONTH] 2025', $after), '[year] [MONTH] 2025');
$check($resolver->cacheLifetime(3600, $before), 1);
$check($resolver->cacheLifetime(3600, $after), 3600);
$twig = new Environment(new ArrayLoader(['title' => '{{ value|title_tokens }}']), ['autoescape' => 'html']);
$twig->addExtension(new TitleTokenExtension($resolver));
$year = (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y');
$check($twig->render('title', ['value' => '<b>[YEAR]</b>']), '&lt;b&gt;'.$year.'&lt;/b&gt;');
foreach (['post', 'page'] as $type) {
    $news = new News();
    $news->setPostType($type);
    $news->setTitle('Title [YEAR]');
    $news->setPageTitle('SEO [YEAR]');
    $check($resolver->resolve($news->getTitle(), $after), 'Title 2027');
    $check($news->getTitle(), 'Title [YEAR]');
    $check($news->getPageTitle(), 'SEO [YEAR]');
}
echo "PASS: year boundary, timezone, repeated tokens, escaping, cache TTL and raw post/page fields.\n";
