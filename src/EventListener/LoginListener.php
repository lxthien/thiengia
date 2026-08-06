<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Service\ActivityLogService;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LoginListener
{
    private $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user && is_object($user)) {
            $this->activityLogService->log(
                ActivityLog::ACTION_LOGIN,
                ActivityLog::ENTITY_USER,
                $user->getId(),
                $user->getUserIdentifier(),
                'Đăng nhập vào hệ thống'
            );
        }
    }
}
