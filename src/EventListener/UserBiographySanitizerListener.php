<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\AuthorBiographySanitizer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class UserBiographySanitizerListener
{
    public function __construct(private readonly AuthorBiographySanitizer $sanitizer) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $user = $args->getObject();
        if ($user instanceof User) {
            $user->setBiography($this->sanitizer->sanitize($user->getBiography()));
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $user = $args->getObject();
        if ($user instanceof User && $args->hasChangedField('biography')) {
            $clean = $this->sanitizer->sanitize($args->getNewValue('biography'));
            $user->setBiography($clean);
            $args->setNewValue('biography', $clean);
        }
    }
}
