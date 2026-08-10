<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Enforces User::$enabled at login. Without this, toggling "Khoá tài khoản"
 * in the admin only flips a DB column with no actual effect — Symfony's
 * core UserInterface has not carried isEnabled()/AdvancedUserInterface
 * since 5.3, so it must be checked explicitly here.
 */
class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isEnabled()) {
            throw new DisabledException('Account is disabled.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
