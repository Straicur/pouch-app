<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$token of method App\\\\Security\\\\CookieServiceInterface\\:\\:prepareAuthCookie\\(\\) expects string, string\\|null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Controller/Api/LoginController.php',
];
$ignoreErrors[] = [
	'message' => '#^Method App\\\\Entity\\\\User\\:\\:getUserIdentifier\\(\\) should return non\\-empty\\-string but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Entity/User.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
