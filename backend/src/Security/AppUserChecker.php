<?php

declare(strict_types = 1);

namespace App\Security;

use App\Entity\User;
use Override;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Wired as the `main` firewall's user_checker (security.yaml) — runs on every
 * authenticated request, not just login, so a JWT issued before an admin
 * disables an account stops working on its own next refresh (access tokens
 * are short-lived, see lexik_jwt_authentication.yaml's token_ttl) rather than
 * staying valid until it naturally expires. AuthService's own check on the
 * login endpoint covers the same case for a *new* login.
 */
final class AppUserChecker implements UserCheckerInterface
{
    #[Override]
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && false === $user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('auth.account_disabled');
        }
    }

    #[Override]
    public function checkPostAuth(UserInterface $user): void
    {
    }
}
