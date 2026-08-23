<?php

declare(strict_types = 1);

use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle;
use Jose\Bundle\JoseFramework\JoseFrameworkBundle;
use Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle;
use Nelmio\ApiDocBundle\NelmioApiDocBundle;
use Nelmio\CorsBundle\NelmioCorsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class               => ['all' => true],
    MakerBundle::class                   => ['dev' => true],
    TwigBundle::class                    => ['all' => true],
    DoctrineBundle::class                => ['all' => true],
    DoctrineMigrationsBundle::class      => ['all' => true],
    MonologBundle::class                 => ['all' => true],
    SecurityBundle::class                => ['all' => true],
    NelmioApiDocBundle::class            => ['all' => true],
    NelmioCorsBundle::class              => ['all' => true],
    LexikJWTAuthenticationBundle::class  => ['all' => true],
    GesdinetJWTRefreshTokenBundle::class => ['all' => true],
    JoseFrameworkBundle::class           => ['all' => true],
    DoctrineFixturesBundle::class        => ['dev' => true, 'test' => true],
    DAMADoctrineTestBundle::class        => ['test' => true],
];
