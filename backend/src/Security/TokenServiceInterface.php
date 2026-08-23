<?php

declare(strict_types = 1);

namespace App\Security;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface TokenServiceInterface
{
    public function createToken(UserInterface $user): string;

    public function createRefreshToken(UserInterface $user): RefreshTokenInterface;
}
