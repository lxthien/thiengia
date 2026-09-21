<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Service\BannerOrderValidator;

$validator = new BannerOrderValidator();
$validator->validate([3, 1, 2], [1, 2, 3], [1, 2, 3]);
$validator->validate([], [], []);
$cases = [
    [[1, 1], [1, 2], [1, 2], InvalidArgumentException::class],
    [['1', 2], [1, 2], [1, 2], InvalidArgumentException::class],
    [[1], [1, 2], [1, 2], InvalidArgumentException::class],
    [[1, 99], [1, 2], [1, 2], InvalidArgumentException::class],
    [[2, 1], [2, 1], [1, 2], LogicException::class],
    [null, [1, 2], [1, 2], InvalidArgumentException::class],
    [[0, 1], [1, 2], [1, 2], InvalidArgumentException::class],
    [[1 => 1, 2 => 2], [1, 2], [1, 2], InvalidArgumentException::class],
];
foreach ($cases as [$items, $expected, $current, $exception]) {
    try {
        $validator->validate($items, $expected, $current);
        throw new RuntimeException('Invalid order was accepted.');
    } catch (Throwable $error) {
        if (get_class($error) !== $exception) throw $error;
    }
}
echo "PASS: valid, empty, duplicate, string, partial, foreign, stale and malformed orders.\n";
