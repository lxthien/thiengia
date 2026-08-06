<?php

namespace App\Service;

use App\Utils\ConvertImages;
use App\Entity\News;
use App\Service\SettingsManager;

class ContentFormatter
{
    private $convertImages;
    private $settingsManager;

    public function __construct(ConvertImages $convertImages, SettingsManager $settingsManager)
    {
        $this->convertImages = $convertImages;
        $this->settingsManager = $settingsManager;
    }

    public function stripTagsContent($string)
    {
        // ----- remove HTML TAGs -----
        $string = preg_replace('/<[^>]*>/', ' ', $string);
        // ----- remove control characters ----- 
        $string = str_replace("\r", '', $string);
        $string = str_replace("\n", ' ', $string);
        $string = str_replace("\t", ' ', $string);

        // ----- remove multiple spaces -----
        $string = trim(preg_replace('/ {2,}/', ' ', $string));

        return $string;
    }

    public function lazyloadContent(News $post)
    {
        $content = $post->getContents();

        // Return early if no content
        if (empty($content)) {
            return '';
        }

        // Replace hardcoded phone numbers with <a href="tel:[hotline_1]">[hotline_1]</a>
        // We use lookaround or specific regex to avoid nesting <a> tags if they are already linked.
        
        $longPhonePattern = '0974[\s\.]*776[\s\.]*305[\s\-\–]*0966[\s\.]*289[\s\.]*559[\s\-\–]*0987[\s\.]*244[\s\.]*305';
        // 1. Replace if it is inside an existing <a> tag
        $content = preg_replace('/<a\b[^>]*>(?:(?!<\/a>).)*?' . $longPhonePattern . '(?:(?!<\/a>).)*?<\/a>/is', '<a href="tel:[hotline_1]">[hotline_1]</a>', $content);
        // 2. Replace bare occurrences
        $content = preg_replace('/' . $longPhonePattern . '/is', '<a href="tel:[hotline_1]">[hotline_1]</a>', $content);

        $shortPhonePattern = '0974[\s\.]*776[\s\.]*305';
        // 1. Replace if it is inside an existing <a> tag
        $content = preg_replace('/<a\b[^>]*>(?:(?!<\/a>).)*?' . $shortPhonePattern . '(?:(?!<\/a>).)*?<\/a>/is', '<a href="tel:[hotline_1]">[hotline_1]</a>', $content);
        // 2. Replace bare occurrences
        $content = preg_replace('/' . $shortPhonePattern . '/is', '<a href="tel:[hotline_1]">[hotline_1]</a>', $content);

        // If you also want to replace other numbers:
        // $content = preg_replace('/0966[\s\.]*289[\s\.]*559/is', '<a href="tel:[hotline_2]">[hotline_2]</a>', $content);
        // $content = preg_replace('/0987[\s\.]*244[\s\.]*305/is', '<a href="tel:[hotline_3]">[hotline_3]</a>', $content);

        // Replace email and ensure it is hyperlinked
        $content = preg_replace('/<a[^>]*href=["\']mailto:xaydungkimanh@gmail\.com["\'][^>]*>.*?<\/a>/i', '<a href="mailto:[email]">[email]</a>', $content);
        $content = preg_replace('/xaydungkimanh@gmail\.com/i', '<a href="mailto:[email]">[email]</a>', $content);

        // Replace placeholders from settings (contens_* or contents_*)
        $content = preg_replace_callback('/\[([a-zA-Z0-9_]+)\]/', function ($matches) {
            $varName = $matches[1];
            // Try contens_ prefix first
            $value = $this->settingsManager->get('contens_' . $varName);
            if ($value !== null) {
                return $value;
            }
            // Try contents_ prefix as fallback
            $value = $this->settingsManager->get('contents_' . $varName);
            if ($value !== null) {
                return $value;
            }
            return $matches[0];
        }, $content);

        // Clean up spaces, dots, commas, dashes from tel: links
        $content = preg_replace_callback('/href=["\']tel:(.*?)["\']/i', function($matches) {
            $cleanedPhone = preg_replace('/[\s\.\,\-\–]+/', '', $matches[1]);
            return 'href="tel:' . $cleanedPhone . '"';
        }, $content);

        // Protect lone "<" characters that are not part of HTML tags
        // Match "<" followed by a digit, space, or other non-tag characters
        $placeholder = '___LESS_THAN_PLACEHOLDER___';
        $content = preg_replace('/<(?=[0-9\s\-\+\=\.\,])/', $placeholder, $content);

        $dom = new \DOMDocument();

        // set error level
        $internalErrors = libxml_use_internal_errors(true);

        // Wrap content to preserve structure and handle UTF-8 properly
        $wrappedContent = '<div id="lazyload-wrapper">' . $content . '</div>';
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $wrappedContent,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        // Remove the XML declaration that was added
        foreach ($dom->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $dom->removeChild($item);
            }
        }

        // Restore error level
        libxml_use_internal_errors($internalErrors);

        $imgs = $dom->getElementsByTagName('img');

        foreach ($imgs as $img) {
            $src = $img->getAttribute('src');
            $alt = $img->getAttribute('alt');

            list($width, $height) = @getimagesize(substr($src, 1));

            $src = !is_bool($this->convertImages->webpConvert2($src, '')) ? $this->convertImages->webpConvert2($src, '') : $src;

            $img->setAttribute('src', '/' . $src);
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('alt', !empty($alt) ? $alt : $post->getTitle());
            $img->setAttribute('width', !empty($width) ? ($width > 900 ? 900 : $width) : 500);
            $img->setAttribute('height', !empty($height) ? ($width > 900 ? round(($height * 900) / $width) : $height) : 500);
        }

        $newContent = $dom->saveHTML();

        // Remove the wrapper div we added
        $newContent = preg_replace('/<div id="lazyload-wrapper">/', '', $newContent);
        $newContent = preg_replace('/<\/div>$/', '', $newContent);

        // Clean up any remaining DOCTYPE, html, head, body tags
        $newContent = preg_replace('/^<!DOCTYPE[^>]*>/i', '', $newContent);
        $newContent = preg_replace('/<\/?html[^>]*>/i', '', $newContent);
        $newContent = preg_replace('/<\/?head[^>]*>/i', '', $newContent);
        $newContent = preg_replace('/<\/?body[^>]*>/i', '', $newContent);

        // Restore the "<" characters
        $newContent = str_replace($placeholder, '<', $newContent);

        return trim($newContent);
    }
}
