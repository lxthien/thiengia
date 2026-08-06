<?php

namespace App\EventListener;

use App\Entity\News;
use App\Entity\NewsCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment as Twig;

/**
 * Intercepts Symfony's automatic trailing-slash redirects for non-existent slugs.
 *
 * When a route is defined as "/{slug}/" and the visitor accesses "/soft-404"
 * (no trailing slash), Symfony normally issues a 301 redirect to "/soft-404/".
 * The controller then throws a 404 — but the browser already followed a redirect.
 *
 * This subscriber detects such trailing-slash redirects and, if the slug does
 * not exist in the database, renders the custom 404 error page directly.
 */
class TrailingSlashSubscriber implements EventSubscriberInterface
{
    private $em;
    private $twig;

    public function __construct(EntityManagerInterface $em, Twig $twig)
    {
        $this->em   = $em;
        $this->twig = $twig;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 64],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Only intercept 301 redirects
        if (!($response instanceof RedirectResponse) || $response->getStatusCode() !== 301) {
            return;
        }

        $request     = $event->getRequest();
        $requestPath = $request->getPathInfo(); // e.g. "/soft-404"
        $targetUrl   = $response->getTargetUrl();
        $targetPath  = parse_url($targetUrl, PHP_URL_PATH); // e.g. "/soft-404/"

        // Is this redirect simply adding a trailing slash?
        if ($targetPath !== $requestPath . '/') {
            return;
        }

        // Parse segments from the destination path
        $segments = array_values(array_filter(explode('/', trim($targetPath, '/'))));

        if (empty($segments)) {
            return;
        }

        // Leave admin and system paths alone
        $ignoredPrefixes = ['admin', 'tag', 'author', 'media', 'comment', 'login', 'logout', 'register', 'resetting'];
        if (in_array($segments[0], $ignoredPrefixes, true)) {
            return;
        }

        // If the slug(s) don't match anything in the DB, render 404 directly
        if (!$this->existsInDatabase($segments)) {
            try {
                $html = $this->twig->render(
                    'bundles/TwigBundle/Exception/error404.html.twig'
                );
                $event->setResponse(new Response($html, 404));
            } catch (\Exception $e) {
                // Fallback if twig render fails (e.g. missing layout assets)
                $event->setResponse(new Response(
                    '<html><body><h1>404 Not Found</h1></body></html>',
                    404,
                    ['Content-Type' => 'text/html']
                ));
            }
        }
    }

    /**
     * Returns true if the URL segments match a known post, page, or category.
     */
    private function existsInDatabase(array $segments): bool
    {
        $slug   = $segments[0] ?? null;
        $level2 = $segments[1] ?? null;
        $level3 = $segments[2] ?? null;

        if ($slug === null) {
            return false;
        }

        // Single segment: /slug/
        if ($level2 === null) {
            $post = $this->em->getRepository(News::class)
                ->findOneBy(['url' => $slug, 'enable' => 1]);
            if ($post) {
                return true;
            }

            $category = $this->em->getRepository(NewsCategory::class)
                ->findOneBy(['url' => $slug, 'enable' => 1]);

            return $category !== null;
        }

        // Two segments: /level1/level2/
        if ($level3 === null) {
            $post = $this->em->getRepository(News::class)
                ->findOneBy(['url' => $level2, 'enable' => 1]);
            if ($post) {
                return true;
            }

            $category = $this->em->getRepository(NewsCategory::class)
                ->findOneBy(['url' => $level2, 'enable' => 1]);

            return $category !== null;
        }

        // Three segments: /level1/level2/level3/
        $post = $this->em->getRepository(News::class)
            ->findOneBy(['url' => $level3, 'enable' => 1]);

        return $post !== null;
    }
}
