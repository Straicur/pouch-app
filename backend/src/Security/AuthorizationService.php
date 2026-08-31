<?php

declare(strict_types = 1);

namespace App\Security;

use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Post-review fix (conceptual — "trait + service" discussion): every
 * controller action used to open with the same three lines —
 * `$this->authService->getUserFromAccessToken()` then `if (false ===
 * $this->isGranted($attribute)) { throw new ForbiddenException(); }` —
 * copy-pasted across ~30 call sites. Pulled out into one place, both for the
 * duplication and so "what does being authorized even mean here" has a
 * single, testable answer instead of thirty identical-by-convention copies.
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
