<?php

namespace App\Service;

use App\Service\SettingsManager;

class PageBuilderService
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    private function replacePlaceholders($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->replacePlaceholders($v);
            }
            return $value;
        }

        if (is_string($value)) {
            return preg_replace_callback('/\[([a-zA-Z0-9_]+)\]/', function ($matches) {
                $varName = $matches[1];
                $val = $this->settingsManager->get('contens_' . $varName);
                if ($val !== null) {
                    return $val;
                }
                $val = $this->settingsManager->get('contents_' . $varName);
                if ($val !== null) {
                    return $val;
                }
                return $matches[0];
            }, $value);
        }

        return $value;
    }

    public function parseBlocks($rawValue)
    {
        if (empty($rawValue)) {
            return [];
        }

        if (is_array($rawValue)) {
            $blocks = $rawValue;
        } else {
            $blocks = json_decode($rawValue, true);
        }

        if (!is_array($blocks)) {
            return [];
        }

        // Replace placeholders recursively in the array structure
        $blocks = $this->replacePlaceholders($blocks);

        return array_values(array_filter(array_map(function ($block) {
            if (!is_array($block) || empty($block['type'])) {
                return null;
            }

            $type = (string) $block['type'];
            $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];

            if ($type === 'video' && !empty($data['url'])) {
                $data['embed_url'] = $this->buildVideoEmbedUrl($data['url']);
            }

            return [
                'id' => isset($block['id']) ? (string) $block['id'] : null,
                'type' => $type,
                'data' => $data,
            ];
        }, $blocks)));
    }

    public function hasBlockType(array $blocks, $type)
    {
        foreach ($blocks as $block) {
            if (isset($block['type']) && $block['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    public function buildLegacyHtmlFromJson($builderData)
    {
        return $this->buildLegacyHtmlFromBlocks($this->parseBlocks($builderData));
    }

    public function buildLegacyHtmlFromBlocks(array $blocks)
    {
        $html = [];

        foreach ($blocks as $block) {
            $blockHtml = $this->buildBlockHtml($block);

            if ($blockHtml !== '') {
                $html[] = $blockHtml;
            }
        }

        return implode("\n", $html);
    }

    public function buildVideoEmbedUrl($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        if (preg_match('~youtube\.com/watch\?v=([^&]+)~', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('~youtu\.be/([^?&/]+)~', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('~vimeo\.com/(\d+)~', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        return $url;
    }

    public function parseLineItems($value, $expectedParts)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            while (count($parts) < $expectedParts) {
                $parts[] = '';
            }

            $items[] = array_slice($parts, 0, $expectedParts);
        }

        return $items;
    }

    private function buildBlockHtml(array $block)
    {
        $type = isset($block['type']) ? (string) $block['type'] : '';
        $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];

        if ($type === 'hero') {
            $heroHtml = '<section class="ka-builder-hero"';

            if (!empty($data['background_image'])) {
                $heroHtml .= ' style="background-image:url(\'' . htmlspecialchars($data['background_image'], ENT_QUOTES, 'UTF-8') . '\')"';
            }

            $heroHtml .= '><div class="ka-builder-hero__inner">';

            if (!empty($data['eyebrow'])) {
                $heroHtml .= '<span class="ka-builder-eyebrow">' . htmlspecialchars($data['eyebrow'], ENT_QUOTES, 'UTF-8') . '</span>';
            }

            if (!empty($data['title'])) {
                $heroHtml .= '<h2>' . htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
            }

            if (!empty($data['body'])) {
                $heroHtml .= '<p>' . nl2br(htmlspecialchars($data['body'], ENT_QUOTES, 'UTF-8')) . '</p>';
            }

            if (!empty($data['button_text']) && !empty($data['button_url'])) {
                $heroHtml .= '<p><a class="ka-builder-button" href="' . htmlspecialchars($data['button_url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($data['button_text'], ENT_QUOTES, 'UTF-8') . '</a></p>';
            }

            return $heroHtml . '</div></section>';
        }

        if ($type === 'image') {
            if (empty($data['url'])) {
                return '';
            }

            $imageHtml = '<figure class="ka-builder-image"';

            if (!empty($data['width'])) {
                $imageHtml .= ' style="max-width:' . htmlspecialchars($data['width'], ENT_QUOTES, 'UTF-8') . 'px"';
            }

            $imageHtml .= '><img loading="lazy" src="' . htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars(isset($data['alt']) ? $data['alt'] : '', ENT_QUOTES, 'UTF-8') . '">';

            if (!empty($data['caption'])) {
                $imageHtml .= '<figcaption>' . htmlspecialchars($data['caption'], ENT_QUOTES, 'UTF-8') . '</figcaption>';
            }

            return $imageHtml . '</figure>';
        }

        if ($type === 'gallery') {
            $items = $this->parseLineItems(isset($data['items']) ? $data['items'] : '', 3);

            if (empty($items)) {
                return '';
            }

            $galleryHtml = '<section class="ka-builder-gallery">';

            foreach ($items as $item) {
                if (empty($item[0])) {
                    continue;
                }

                $galleryHtml .= '<figure class="ka-builder-gallery__item">';
                $galleryHtml .= '<img loading="lazy" src="' . htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars(isset($item[1]) ? $item[1] : '', ENT_QUOTES, 'UTF-8') . '">';

                if (!empty($item[2])) {
                    $galleryHtml .= '<figcaption>' . htmlspecialchars($item[2], ENT_QUOTES, 'UTF-8') . '</figcaption>';
                }

                $galleryHtml .= '</figure>';
            }

            return $galleryHtml . '</section>';
        }

        if ($type === 'faq') {
            $items = $this->parseLineItems(isset($data['items']) ? $data['items'] : '', 2);

            if (empty($items)) {
                return '';
            }

            $faqHtml = '<section class="ka-builder-faq">';

            foreach ($items as $item) {
                if (empty($item[0])) {
                    continue;
                }

                $faqHtml .= '<details class="ka-builder-faq__item">';
                $faqHtml .= '<summary>' . htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8') . '</summary>';
                $faqHtml .= '<div class="ka-builder-faq__answer"><p>' . nl2br(htmlspecialchars(isset($item[1]) ? $item[1] : '', ENT_QUOTES, 'UTF-8')) . '</p></div>';
                $faqHtml .= '</details>';
            }

            return $faqHtml . '</section>';
        }

        if ($type === 'video') {
            $embedUrl = $this->buildVideoEmbedUrl(isset($data['url']) ? $data['url'] : '');

            if ($embedUrl === '') {
                return '';
            }

            $videoHtml = '<section class="ka-builder-video">';

            if (!empty($data['title'])) {
                $videoHtml .= '<h3>' . htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
            }

            $videoHtml .= '<div class="ka-builder-video__frame"><iframe src="' . htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') . '" allowfullscreen loading="lazy"></iframe></div>';

            if (!empty($data['caption'])) {
                $videoHtml .= '<p class="ka-builder-video__caption">' . nl2br(htmlspecialchars($data['caption'], ENT_QUOTES, 'UTF-8')) . '</p>';
            }

            return $videoHtml . '</section>';
        }

        if ($type === 'features') {
            $items = $this->parseLineItems(isset($data['items']) ? $data['items'] : '', 3);

            if (empty($items)) {
                return '';
            }

            $featuresHtml = '<section class="ka-builder-features">';

            if (!empty($data['title'])) {
                $featuresHtml .= '<h3>' . htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
            }

            $featuresHtml .= '<div class="ka-builder-features__grid">';

            foreach ($items as $item) {
                $featuresHtml .= '<article class="ka-builder-features__item">';

                if (!empty($item[0])) {
                    $featuresHtml .= '<div class="ka-builder-features__icon"><i class="' . htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i></div>';
                }

                if (!empty($item[1])) {
                    $featuresHtml .= '<h4>' . htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') . '</h4>';
                }

                if (!empty($item[2])) {
                    $featuresHtml .= '<p>' . nl2br(htmlspecialchars($item[2], ENT_QUOTES, 'UTF-8')) . '</p>';
                }

                $featuresHtml .= '</article>';
            }

            return $featuresHtml . '</div></section>';
        }

        if ($type === 'contact_form') {
            $contactHtml = '<section class="ka-builder-contact-form">';

            if (!empty($data['title'])) {
                $contactHtml .= '<h3>' . htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
            }

            if (!empty($data['body'])) {
                $contactHtml .= '<p>' . nl2br(htmlspecialchars($data['body'], ENT_QUOTES, 'UTF-8')) . '</p>';
            }

            if (!empty($data['hotline'])) {
                $contactHtml .= '<p><strong>' . htmlspecialchars($data['hotline'], ENT_QUOTES, 'UTF-8') . '</strong></p>';
            }

            return $contactHtml . '<div class="ka-builder-contact-form__placeholder">[contact form]</div></section>';
        }

        if ($type === 'cta') {
            $ctaHtml = '<section class="ka-builder-cta ka-builder-cta--' . htmlspecialchars(!empty($data['style']) ? $data['style'] : 'primary', ENT_QUOTES, 'UTF-8') . '">';

            if (!empty($data['title'])) {
                $ctaHtml .= '<h3>' . htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
            }

            if (!empty($data['body'])) {
                $ctaHtml .= '<p>' . nl2br(htmlspecialchars($data['body'], ENT_QUOTES, 'UTF-8')) . '</p>';
            }

            if (!empty($data['button_text']) && !empty($data['button_url'])) {
                $ctaHtml .= '<a class="ka-builder-button" href="' . htmlspecialchars($data['button_url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($data['button_text'], ENT_QUOTES, 'UTF-8') . '</a>';
            }

            return $ctaHtml . '</section>';
        }

        if ($type === 'spacer') {
            return '<div class="ka-builder-spacer" style="height:' . htmlspecialchars(!empty($data['height']) ? $data['height'] : '48', ENT_QUOTES, 'UTF-8') . 'px"></div>';
        }

        return '<section class="ka-builder-rich-text">' . (!empty($data['html']) ? $data['html'] : '') . '</section>';
    }
}
