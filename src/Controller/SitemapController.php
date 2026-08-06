<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\SitemapService;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap')]
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
