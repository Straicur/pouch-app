<?php

declare(strict_types = 1);

// There's no secrets vault for the test env (only config/secrets/dev/ exists), so
// JWT_PRIVATE_TOKEN/JWT_PUBLIC_TOKEN can't come from there like they do for dev —
// generate a standalone JWK pair straight into .env.test.local instead. Called by
// `make test-env-jwt`.

require __DIR__ . '/../vendor/autoload.php';

use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;

$envTestLocal = __DIR__ . '/../.env.test.local';

if (str_contains((string) file_get_contents($envTestLocal), 'JWT_PASSPHRASE=')) {
    exit(0);
}

$jwk = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig']);
$publicKeySet = new JWKSet([$jwk->toPublic()]);

file_put_contents(
    $envTestLocal,
    'JWT_PASSPHRASE=test' . \PHP_EOL
        . 'JWT_PRIVATE_TOKEN=' . \escapeshellarg(json_encode($jwk->jsonSerialize())) . \PHP_EOL
        . 'JWT_PUBLIC_TOKEN=' . \escapeshellarg(json_encode($publicKeySet->jsonSerialize())) . \PHP_EOL,
    \FILE_APPEND,
);
