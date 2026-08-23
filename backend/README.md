# MVC_Template

Starter template for a Symfony 7.4 JSON API: JWT authentication (access + refresh tokens via
HttpOnly cookies), a typed exception-to-JSON-response pipeline, request DTO validation, and
OpenAPI docs (Nelmio) out of the box.

## Stack

- PHP 8.4, Symfony 7.4
- Doctrine ORM + Migrations, PostgreSQL
- `lexik/jwt-authentication-bundle` + `gesdinet/jwt-refresh-token-bundle`
- `nelmio/api-doc-bundle` for OpenAPI/Swagger UI
- PHPUnit, PHPStan (level 10 + strict rules), PHP-CS-Fixer, Rector

## Getting started

1. Copy the env file and adjust it if needed:
   ```
   cp .env .env.local
   ```
2. Generate the JWT key pair (required by `lexik/jwt-authentication-bundle`):
   ```
   docker compose run --rm app php bin/console lexik:jwt:generate-keypair
   ```
3. Start the stack (app, nginx, postgres):
   ```
   docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
   ```
4. Install dependencies (if not already installed by the image build):
   ```
   docker compose exec app composer install
   ```
5. Run database migrations:
   ```
   make migrate
   ```
6. (Optional) Load fixtures:
   ```
   make fixtures
   ```

The app is then available at `http://localhost:8111`, with the API docs at
`http://localhost:8111/api/doc`.

All `make`/`composer` targets below assume they run inside the `app` container
(`docker compose exec app <command>`), unless you have a local PHP 8.4 toolchain.

## Common tasks

| Command | Description |
| --- | --- |
| `make migration` | Create a new Doctrine migration |
| `make migrate` | Apply migrations (dev + test databases) |
| `make entity` | Generate a new entity |
| `make fixtures` | Load data fixtures |
| `make test` | Run the PHPUnit test suite |
| `make cs` / `make cs-fix` | Check / fix code style (PHP-CS-Fixer) |
| `make rector` / `make rector-fix` | Preview / apply Rector refactors |
| `make phpstan` | Run static analysis |

## Project layout

- `src/Controller/Api` — API controllers, documented with OpenAPI attributes.
- `src/DTO/Request` / `src/DTO/Response` — request/response payloads, validated via
  `Symfony\Component\Validator`.
- `src/Service/RequestService` — deserializes and validates incoming request bodies into DTOs.
- `src/Security` — auth, cookie and config services backing JWT login/logout/refresh.
- `src/ExceptionManagement` — typed API/server exceptions mapped to JSON error responses by
  `ExceptionSubscriber`.

## Testing

Tests use `dama/doctrine-test-bundle` to wrap each test in a transaction that's rolled back
afterwards, so the test database stays clean between runs.

```
make test
```