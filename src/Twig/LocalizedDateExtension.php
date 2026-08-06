<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides the legacy `localizeddate` Twig filter that was removed
 * from twig/extensions. Uses PHP's IntlDateFormatter when available,
 * falls back to DateTime::format().
 */
class LocalizedDateExtension extends AbstractExtension
{
    private const DATE_FORMATS = [
        'none'   => \IntlDateFormatter::NONE,
        'short'  => \IntlDateFormatter::SHORT,
        'medium' => \IntlDateFormatter::MEDIUM,
        'long'   => \IntlDateFormatter::LONG,
        'full'   => \IntlDateFormatter::FULL,
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('localizeddate', [$this, 'localizedDate']),
        ];
    }

    /**
     * @param \DateTimeInterface|string $date
     */
    public function localizedDate(
        $date,
        string $dateFormat = 'medium',
        string $timeFormat = 'medium',
        ?string $locale = null,
        ?string $timezone = null,
        ?string $pattern = null
    ): string {
        if (!$date instanceof \DateTimeInterface) {
            if (is_string($date)) {
                $date = new \DateTime($date);
            } else {
                return '';
            }
        }

        // Use IntlDateFormatter if available
        if (class_exists(\IntlDateFormatter::class)) {
            $dfDate = self::DATE_FORMATS[$dateFormat] ?? \IntlDateFormatter::MEDIUM;
            $dfTime = self::DATE_FORMATS[$timeFormat] ?? \IntlDateFormatter::MEDIUM;

            $formatter = new \IntlDateFormatter(
                $locale ?? \Locale::getDefault(),
                $dfDate,
                $dfTime,
                $timezone,
                null,
                $pattern ?? ''
            );

            $result = $formatter->format($date);
            return $result !== false ? $result : $date->format('d/m/Y H:i');
        }

        // Fallback without intl extension
        return $date->format('d/m/Y H:i');
    }
}
