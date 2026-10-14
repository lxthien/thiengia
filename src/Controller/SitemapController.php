<?php

namespace App\Controller;

use App\Seo\IndexNow\IndexNowClient;
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

    #[Route('/robots.txt', name: 'robots_txt', methods: ['GET'])]
    public function robotsAction(): Response
    {
        $response = new Response(
            $this->renderView('Sitemap/robots.txt.twig'),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
        $response->setPublic();
        $response->setMaxAge(300);
        $response->setSharedMaxAge(300);

        return $response;
    }

    /**
     * File khóa IndexNow: công cụ tìm kiếm tải /<key>.txt để xác minh site
     * thật sự sở hữu khóa đã dùng khi gửi URL. Chỉ trả lời đúng khóa đang
     * cấu hình (INDEXNOW_KEY), mọi tên khác là 404.
     */
    #[Route('/{key}.txt', name: 'indexnow_key', requirements: ['key' => '[a-f0-9]{8,128}'], methods: ['GET'], priority: 10)]
    public function indexNowKeyAction(string $key, IndexNowClient $indexNow): Response
    {
        if (!$indexNow->isEnabled() || !hash_equals($indexNow->getKey(), $key)) {
            throw $this->createNotFoundException();
        }

        return new Response($indexNow->getKey(), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
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
