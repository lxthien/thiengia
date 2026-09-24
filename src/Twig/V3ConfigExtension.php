<?php

namespace App\Twig;

use App\Service\V3Config;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Cung cấp biến global `v3` cho template — xem App\Service\V3Config.
 */
final class V3ConfigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly V3Config $config)
    {
    }

    public function getGlobals(): array
    {
        return ['v3' => $this->config->all()];
    }
}
