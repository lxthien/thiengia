<?php

namespace App\Controller;

use App\Service\SitemapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemapAction(Request $request, SitemapService $sitemapService): Response
    {
        return $this->createXmlResponse(
            $request,
            $this->renderView('Sitemap/sitemap_index.xml.twig', [
                'sitemaps' => $sitemapService->generateSitemapIndex(),
            ]),
            'sitemap-index-' . $sitemapService->getVersion(),
        );
    }

    #[Route('/sitemap-{page}.xml', name: 'sitemap_page', requirements: ['page' => '[1-9]\\d*'], methods: ['GET'])]
    public function sitemapPageAction(Request $request, int $page, SitemapService $sitemapService): Response
    {
        if ($page > $sitemapService->getSitemapPageCount()) {
            throw $this->createNotFoundException('Sitemap page does not exist.');
        }

        return $this->createXmlResponse(
            $request,
            $this->renderView('Sitemap/sitemap.xml.twig', [
                'urls' => $sitemapService->generateSitemapPage($page),
            ]),
            'sitemap-page-' . $page . '-' . $sitemapService->getVersion(),
        );
    }

    private function createXmlResponse(Request $request, string $xml, string $etag): Response
    {
        $response = new Response(
            $xml,
            Response::HTTP_OK,
            ['Content-Type' => 'application/xml; charset=utf-8']
        );
        $response->setPublic();
        $response->setMaxAge(300);
        $response->setSharedMaxAge(300);
        $response->setEtag(hash('sha256', $etag));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
