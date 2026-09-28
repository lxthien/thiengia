<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TitleTokenResolver
{
    private readonly \DateTimeZone $timezone;

    public function __construct(#[Autowire('%app.timezone%')] string $timezone)
    {
        $this->timezone = new \DateTimeZone($timezone);
    }

    public function resolve(?string $value, ?\DateTimeImmutable $now = null): string
    {
        $now = ($now ?? new \DateTimeImmutable())->setTimezone($this->timezone);
        return str_replace('[YEAR]', $now->format('Y'), $value ?? '');
    }

    public function cacheLifetime(int $maximum, ?\DateTimeImmutable $now = null): int
    {
        $now = ($now ?? new \DateTimeImmutable())->setTimezone($this->timezone);
        $nextYear = $now->setDate((int) $now->format('Y') + 1, 1, 1)->setTime(0, 0);
        return max(0, min($maximum, $nextYear->getTimestamp() - $now->getTimestamp()));
    }
}
