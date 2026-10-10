<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Form\GlobalSettingsType;
use Symfony\Component\Form\Forms;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

$settings = [];
$twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'));
$twig->addFunction(new TwigFunction('get_setting', static function ($key, $default = '') use (&$settings) { return $settings[$key] ?? $default; }));
$twig->addFunction(new TwigFunction('path', static fn ($route, $params = []) => '/'.$route));
$twig->addFunction(new TwigFunction('asset', static fn ($path) => '/'.$path));
$context = ['v3' => ['company' => ['shortName' => 'Thiện Gia', 'footerIntro' => 'Giới thiệu', 'legalName' => 'Công ty Thiện Gia', 'slogan' => '', 'address' => '', 'phoneRaw' => '', 'phone' => '', 'email' => '', 'workingHours' => ''], 'menu' => ['services' => []], 'newsCategorySlug' => 'cam-nang']];
$check = static function ($condition, $message) { if (!$condition) { throw new RuntimeException($message); } };
$html = $twig->render('v3/layout/_footer.html.twig', $context);
$check(!str_contains($html, 'class="ft-socials"'), 'Empty links must hide social navigation');
$settings = ['linkToZalo' => 'https://zalo.me/0900000000', 'linkToFacebook' => 'https://facebook.com/example', 'linkToYoutube' => 'https://youtube.com/@example', 'linkToTiktok' => 'https://tiktok.com/@example', 'linkToTwitter' => 'https://x.com/example', 'linkToLinkedin' => 'https://linkedin.com/company/example', 'linkToInstagram' => 'https://instagram.com/example', 'linkToGooglePlus' => 'https://plus.google.com/legacy'];
$html = $twig->render('v3/layout/_footer.html.twig', $context);
$check(substr_count($html, 'class="ft-social"') === 7, 'All seven configured channels must appear');
$check(substr_count($html, '<path d=') === 7, 'Every channel must have an SVG glyph');
$check(!str_contains($html, 'plus.google.com'), 'Legacy Google+ must not become TikTok');
$check(str_contains($html, 'thiengia-logo-footer.svg'), 'Dedicated footer logo must remain');
$check(substr_count($html, 'rel="noopener noreferrer"') === 7, 'New tab links must be protected');
if (in_array('--html', $argv, true)) { echo $html; exit; }
$settings = ['linkToFacebook' => 'javascript:alert(1)', 'linkToTiktok' => 'https://tiktok.com/a b'];
$check(!str_contains($twig->render('v3/layout/_footer.html.twig', $context), 'class="ft-socials"'), 'Unsafe and malformed links must be hidden');
$form = Forms::createFormFactory()->create(GlobalSettingsType::class, ['linkToGooglePlus' => 'legacy']);
$check($form->has('linkToZalo') && $form->has('linkToTiktok') && !$form->has('linkToGooglePlus'), 'Admin must expose Zalo and TikTok instead of Google+');
$check($form->get('linkToTiktok')->getConfig()->getOption('label') === 'Link TikTok', 'TikTok label must be correct');
echo "Footer social smoke checks passed.\n";
