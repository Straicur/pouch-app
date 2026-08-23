<?php

declare(strict_types = 1);

namespace App\Security;

use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;

interface AuthServiceInterface
{
    /**
     * @throws UnauthorizedException
     */
    public function getUserByEmailAndPassword(string $email, string $password): User;

    /**
     * @throws UnauthorizedException
     */
    public function getUserFromAccessToken(): User;
}
