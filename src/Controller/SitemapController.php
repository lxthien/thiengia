<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use App\Service\SitemapService;

class SitemapController extends Controller
{
    /**
     * @Route("/sitemap.xml", name="sitemap")
     */
    public function sitemapAction(SitemapService $sitemapService)
    {
        $urls = $sitemapService->generateSitemap();

        $response = new Response(
            $this->renderView('Sitemap/sitemap.xml.twig', [
                'urls' => $urls
            ]),
            200,
            ['Content-Type' => 'application/xml; charset=utf-8']
        );

        return $response;
    }
}
