<?php

namespace App\EventListener;

use App\Entity\Redirect;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class RedirectSubscriber implements EventSubscriberInterface
{
    private $em;
    private $router;

    public function __construct(EntityManagerInterface $em, RouterInterface $router)
    {
        $this->em = $em;
        $this->router = $router;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 64],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        // Prevent redirect loop in admin area
        if (strpos($pathInfo, '/admin/') === 0 || strpos($pathInfo, '/_profiler') === 0) {
            return;
        }

        $redirects = $this->em->getRepository(Redirect::class)->findBy(
            ['isActive' => true],
            ['orderNum' => 'ASC', 'id' => 'DESC']
        );

        foreach ($redirects as $redirect) {
            if ($this->match($pathInfo, $redirect)) {
                $destination = $redirect->getDestinationUrl();
                // Avoid self-redirect loop if they matched somehow
                if ($pathInfo !== $destination) {
                    $response = new RedirectResponse($destination, $redirect->getStatusCode());
                    $event->setResponse($response);
                    return;
                }
            }
        }
    }

    private function match(string $pathInfo, Redirect $redirect): bool
    {
        $source = $redirect->getSourceUrl();
        $type = $redirect->getMatchType();

        switch ($type) {
            case 'exact':
                return $pathInfo === $source;

            case 'wildcard':
                // Convert * to regex .*
                $pattern = preg_quote($source, '#');
                $pattern = str_replace('\*', '.*', $pattern);
                return (bool) preg_match('#^' . $pattern . '$#i', $pathInfo);

            case 'regex':
                // Attempt to match with regex. Add delimiters if missing.
                $pattern = $source;
                if (strpos($pattern, '/') !== 0 && strpos($pattern, '#') !== 0) {
                    $pattern = '#' . $pattern . '#i';
                }
                
                // Suppress warning in case of invalid regex
                $matched = @preg_match($pattern, $pathInfo);
                return $matched === 1;

            default:
                return false;
        }
    }
}
