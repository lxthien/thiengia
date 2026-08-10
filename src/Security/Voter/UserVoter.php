<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Super Admin quản lý được mọi user. Admin quản lý được Editor/Author nhưng
 * không đụng được tài khoản Admin/Super Admin khác. Ai cũng tự quản lý được
 * hồ sơ của chính mình (controller/form phải tự khoá field roles khi tự sửa
 * mà không phải Super Admin — Voter chỉ trả lời "được vào action hay không").
 */
class UserVoter extends Voter
{
    public const MANAGE = 'USER_MANAGE';

    public function __construct(private readonly AuthorizationCheckerInterface $authChecker)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User) {
            return false;
        }

        if ($this->authChecker->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        /** @var User $target */
        $target = $subject;

        if ($actor->getId() === $target->getId()) {
            return true;
        }

        $targetIsPrivileged = array_intersect(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $target->getRoles()) !== [];

        return $this->authChecker->isGranted('ROLE_ADMIN') && !$targetIsPrivileged;
    }
}
