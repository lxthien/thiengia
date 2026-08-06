<?php

namespace App\Twig;

use App\Service\SettingsManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_setting', [$this, 'getSetting']),
        ];
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settingsManager->get($key, $default);
    }
}
