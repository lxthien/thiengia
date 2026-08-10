<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Service\ActivityLogService;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

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

    public function onLoginFailure(LoginFailureEvent $event)
    {
        $attemptedUsername = $event->getRequest()->request->get('_username');

        // hide_user_not_found (mặc định bật) khiến AuthenticatorManager thay AccountStatusException
        // gốc (VD: tài khoản bị khoá) bằng BadCredentialsException chung chung trước khi tới đây,
        // để tránh lộ thông tin ra ngoài (chống dò tài khoản qua thông báo lỗi). Exception gốc vẫn
        // được giữ lại qua getPrevious() — dùng nó để log đúng lý do cho riêng admin xem.
        $exception = $event->getException();
        $original = $exception->getPrevious() ?: $exception;

        $reason = $original instanceof AccountStatusException
            ? 'Tài khoản bị khoá hoặc vô hiệu hoá'
            : 'Sai tên đăng nhập hoặc mật khẩu';

        $this->activityLogService->log(
            ActivityLog::ACTION_LOGIN_FAILED,
            ActivityLog::ENTITY_USER,
            null,
            $attemptedUsername ?: '(không rõ)',
            $reason
        );
    }
}
