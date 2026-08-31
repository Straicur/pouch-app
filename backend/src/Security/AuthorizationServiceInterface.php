<?php

declare(strict_types = 1);

namespace App\Security;

use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;

/**
 * The "must be logged in, and allowed to do this" check every controller
 * action opens with — see AuthorizesRequestsTrait (src/ControllerHelper) for
 * the thin per-action shorthand this backs.
 */
interface AuthorizationServiceInterface
{
    /**
     * @throws UnauthorizedException if there's no valid access token at all
     * @throws ForbiddenException    if logged in, but $attribute isn't granted
     */
    public function assertGranted(string $attribute, mixed $subject = null): User;
}
