<?php

declare(strict_types = 1);

namespace App\Security;

use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The one place "does this request have the right role" is answered — every
 * controller action calls this instead of repeating the same auth-then-voter
 * check inline, so the answer stays single and testable.
 *
 * `Symfony\Bundle\SecurityBundle\Security::isGranted()` here, not
 * `AbstractController::isGranted()` — this is a plain service, not a
 * controller, so it needs the real thing voters/`isGranted()` are built on
 * (same as AccessKeyGuard/CategoryVoter already do), not the
 * controller-only shortcut.
 */
final readonly class AuthorizationService implements AuthorizationServiceInterface
{
    public function __construct(
        private AuthServiceInterface $authService,
        private Security $security,
    ) {}

    #[Override]
    public function assertGranted(string $attribute, mixed $subject = null): User
    {
        $user = $this->authService->getUserFromAccessToken();

        if (false === $this->security->isGranted($attribute, $subject)) {
            throw new ForbiddenException();
        }

        return $user;
    }
}
