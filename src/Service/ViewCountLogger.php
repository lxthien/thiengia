<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class ViewCountLogger
{
    private $logDir;

    public function __construct($logDir)
    {
        $this->logDir = rtrim($logDir, '/\\');
    }

    public function logView($postId, Request $request)
    {
        $userAgent = $request->headers->get('User-Agent', '');
        
        if ($this->isBot($userAgent)) {
            return;
        }

        $logFile = $this->logDir . '/view_counts.log';
        // Append postId to log with newline, using exclusive lock to prevent truncation from concurrent requests
        file_put_contents($logFile, $postId . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function isBot($userAgent)
    {
        if (empty($userAgent)) {
            return true;
        }

        $userAgent = strtolower($userAgent);

        // 1. Check if User-Agent contains 'bot' (excluding legitimate 'cubot' mobile brand)
        if (strpos($userAgent, 'bot') !== false && strpos($userAgent, 'cubot') === false) {
            return true;
        }

        // 2. Check other known bot/crawler keywords and common HTTP libraries
        $botKeywords = [
            'crawl',
            'spider',
            'slurp',
            'mediapartners',
            'screaming frog',
            'facebookexternalhit',
            'whatsapp',
            // Common HTTP client libraries / tools used for scraping/crawling
            'curl',
            'wget',
            'guzzle',
            'httpclient',
            'http-client',
            'http_client',
            'python',
            'requests',
            'urllib',
            'go-http',
            'okhttp',
            'node-fetch',
            'axios',
            'needle',
            'postman',
            'insomnia',
            'headless',
            'puppeteer',
            'playwright',
            'selenium',
            'scrapy',
            'scraping',
            'rest-client',
            'ruby',
            'perl',
            'java/',
        ];

        foreach ($botKeywords as $keyword) {
            if (strpos($userAgent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
