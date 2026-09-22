<?php
require dirname(__DIR__, 2) . '/src/Health/InternalUrlNormalizer.php';
use App\Health\InternalUrlNormalizer;
$cases = [
 ['/bai-viet.html', '/bai-viet.html'],
 ['bai-viet.html?ref=1#noidung', '/bai-viet.html'],
 ['https://example.test/a?x=1&amp;y=2', '/a'],
 ['http://EXAMPLE.TEST:8080/a', '/a'],
 ['//example.test/a', '/a'],
 ['https://example.test', '/'],
 ['https://outside.test/a', null],
 ['//outside.test/a', null],
 ['https://example.test.outside.test/a', null],
 ['https://outside.test@example.test/a', null],
 ['mailto:abc@example.test', null],
 ['tel:123', null],
 ['javascript:alert(1)', null],
 ['ftp://example.test/a', null],
 ['data:text/plain,hello', null],
 ['#content', null],
 ['?page=2', null],
 ['http:///broken', null],
 ['', null],
];
unset($_SERVER['HTTP_HOST']);
foreach ($cases as [$url, $expected]) {
 $actual = InternalUrlNormalizer::normalize($url, 'example.test');
 if ($actual !== $expected) { throw new RuntimeException('Mismatch: ' . $url); }
}
if (InternalUrlNormalizer::normalize('https://outside.test/a', '') !== null) {
 throw new RuntimeException('Unknown host must not treat remote URLs as local');
}
echo "PASS: URL classification, external domains, CLI without HTTP_HOST, schemes and anchors.\n";
