<?php

namespace App\EventListener;

use App\Entity\Redirect;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RedirectSubscriber implements EventSubscriberInterface
{
    public const CACHE_KEY = 'app.redirects.active_rules';

    // Giới hạn số chặng resolve trong 1 request — nếu chạm ngưỡng này mà
    // vẫn còn rule khớp thì coi như cấu hình có vấn đề, dừng lại an toàn.
    private const MAX_HOPS = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
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

        $redirects = $this->getActiveRedirects();
        if (empty($redirects)) {
            return;
        }

        // Resolve toàn bộ chuỗi redirect ngay trong request này (thay vì để
        // trình duyệt tự đi từng chặng một) — vừa tránh redirect chain (mỗi
        // chặng thêm 1 round-trip, xấu cho SEO/tốc độ), vừa cho phép phát
        // hiện vòng lặp một cách chắc chắn: nếu chuỗi quay lại URL đã đi qua
        // trong CHÍNH request đang xử lý, bỏ qua redirect hoàn toàn (không
        // trả response nào) thay vì đẩy trình duyệt vào vòng lặp — cách này
        // an toàn bất kể người dùng vào từ điểm nào trong vòng lặp, vì mỗi
        // request luôn tự phát hiện và tự chặn trước khi trả về bất kỳ
        // redirect nào.
        $current = $pathInfo;
        $finalStatusCode = null;
        $visited = [$pathInfo => true];
        $hops = 0;

        while ($hops < self::MAX_HOPS) {
            $matchedRedirect = null;

            foreach ($redirects as $redirect) {
                if ($this->match($current, $redirect)) {
                    $matchedRedirect = $redirect;
                    break;
                }
            }

            if ($matchedRedirect === null) {
                break;
            }

            $destination = $matchedRedirect['destinationUrl'];

            if (isset($visited[$destination])) {
                $this->logger->error('Redirect loop detected, skipping redirect entirely', [
                    'path' => $pathInfo,
                    'looped_at' => $destination,
                ]);
                return;
            }

            $visited[$destination] = true;
            $finalStatusCode = $matchedRedirect['statusCode'];
            $current = $destination;
            $hops++;
        }

        if ($current !== $pathInfo) {
            $response = new RedirectResponse($current, $finalStatusCode);
            $response->setPublic();
            $response->setMaxAge(3600);
            $event->setResponse($response);
        }
    }

    /**
     * Trả về mảng dữ liệu thuần (không phải entity Doctrine) — cache pool
     * generic (FilesystemAdapter) serialize entity trực tiếp có thể kẹt/lỗi
     * do proxy/UnitOfWork, nên chỉ cache scalar.
     *
     * @return array<int, array{sourceUrl: string, destinationUrl: string, matchType: string, statusCode: int}>
     */
    private function getActiveRedirects(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(300);

            $rows = $this->em->createQueryBuilder()
                ->select('r.sourceUrl', 'r.destinationUrl', 'r.matchType', 'r.statusCode')
                ->from(Redirect::class, 'r')
                ->where('r.isActive = true')
                ->orderBy('r.orderNum', 'ASC')
                ->addOrderBy('r.id', 'DESC')
                ->getQuery()
                ->getArrayResult();

            return $rows;
        });
    }

    /**
     * @param array{sourceUrl: string, destinationUrl: string, matchType: string, statusCode: int} $redirect
     */
    private function match(string $pathInfo, array $redirect): bool
    {
        $source = $redirect['sourceUrl'];
        $type = $redirect['matchType'];

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
