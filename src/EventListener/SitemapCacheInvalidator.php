<?php

namespace App\EventListener;

use App\Entity\News;
use App\Entity\NewsCategory;
use App\Service\SitemapService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\CacheInterface;

/** Clears the short-lived sitemap manifest immediately after SEO-relevant changes. */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class SitemapCacheInvalidator
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateWhenNeeded($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateWhenNeeded($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateWhenNeeded($args->getObject());
    }

    private function invalidateWhenNeeded(object $entity): void
    {
        if ($entity instanceof News || $entity instanceof NewsCategory) {
            $this->cache->delete(SitemapService::MANIFEST_CACHE_KEY);
        }
    }
}
