<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Traits;

use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\Security\AuthorizationServiceInterface;

/**
 * Thin delegate, not a second place logic lives — AuthorizationService holds
 * the actual "must be logged in and allowed to do this" check
 * (App\Security\AuthorizationService), independently testable/mockable.
 * This only exists to shorten the call site: `$this->assertGranted($attr)`
 * instead of `$this->authorizationService->assertGranted($attr)` in every
 * single action.
 *
 * Relies on the using controller constructor-promoting
 * `private readonly AuthorizationServiceInterface $authorizationService` —
 * every controller in this app already promotes its dependencies with that
 * exact naming discipline (see `$authService`/`$serializer` everywhere
 * else), so this isn't a new, fragile assumption, just formalizing an
 * existing one. `@property-read` below gives PHPStan/IDEs the same
 * visibility a real property declaration would.
 *
 * @property-read AuthorizationServiceInterface $authorizationService
 */
trait AuthorizesRequestsTrait
{
    /**
     * @throws UnauthorizedException if there's no valid access token at all
     * @throws ForbiddenException    if logged in, but $attribute isn't granted
     */
    protected function assertGranted(string $attribute, mixed $subject = null): User
    {
        return $this->authorizationService->assertGranted($attribute, $subject);
    }
}
