<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Service\Homepage\SectionIcons;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

$check = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables' => true]);
$twig->addFunction(new TwigFunction('asset', static fn ($path) => $path));
$twig->addFunction(new TwigFunction('absolute_url', static fn ($path) => 'https://example.com'.$path));
$twig->addFunction(new TwigFunction('section_icon', [SectionIcons::class, 'markup'], ['is_safe' => ['html']]));
$twig->addFilter(new TwigFilter('imagine_filter', static fn ($path, $filter) => '/media/cache/'.$filter.$path));
$twig->addGlobal('convert_images', new class { public function fileExists($path): bool { return $path === '/uploads/media/architecture.jpg'; } });
$data = [
    'v3' => ['pageHeroImage' => '/uploads/media/architecture.jpg'],
    'breadcrumb' => [['label' => 'Trang chủ', 'path' => '/'], ['label' => 'Về Thiện Gia', 'path' => '/ve-thien-gia/'], ['label' => 'Chính sách bảo hành']],
    'title' => 'Chính sách bảo hành', 'description' => 'Thông tin bảo hành của Thiện Gia.',
];
$html = $twig->render('v3/components/_page_hero.html.twig', $data);
$check(str_contains($html, 'page-hero-media') && str_contains($html, 'page_hero_architecture'), 'Existing architecture media must use optimized filter');
$check(str_contains($html, 'breadcrumb-v3-home') && str_contains($html, 'href="/ve-thien-gia/"'), 'Home icon and ancestor links must remain');
$check(substr_count($html, 'aria-current="page"') === 1, 'Exactly one current page must be identified');
preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
$schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
$check($schema['@type'] === 'BreadcrumbList' && count($schema['itemListElement']) === 3, 'SEO breadcrumb must preserve all levels');
$data['v3']['pageHeroImage'] = '/missing.jpg';
$check(!str_contains($twig->render('v3/components/_page_hero.html.twig', $data), 'class="page-hero-media"'), 'Missing image must not emit broken media');
$data['breadcrumb'][2]['label'] = '</script><script>alert(1)</script>';
$html = $twig->render('v3/components/_page_hero.html.twig', $data);
$check(!str_contains($html, '<script>alert(1)'), 'Breadcrumb labels must be safe in text and JSON-LD');
echo "Page hero smoke checks passed.\n";
