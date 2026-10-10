<?php

namespace App\Service;

use HTMLPurifier;
use HTMLPurifier_Config;

final class AuthorBiographySanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function sanitize(?string $html): string
    {
        if (!$html) { return ''; }
        if (!$this->purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', 'p,br,strong,em,ul,ol,li,a[href|title|target|rel]');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer']);
            $config->set('HTML.TargetNoopener', true);
            $config->set('HTML.TargetNoreferrer', true);
            $this->purifier = new HTMLPurifier($config);
        }
        return $this->purifier->purify($html);
    }
}
