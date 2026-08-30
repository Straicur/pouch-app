# Pouch

A private "pouch" for links, photos and files — a self-hosted alternative to hoarding
everything in Discord.

Monorepo: Symfony API backend + React SPA frontend, run together via Docker Compose.

- [`backend/`](backend/README.md) — Symfony 7.4 JSON API (JWT auth, Doctrine/PostgreSQL,
  OpenAPI docs).
- [`frontend/`](frontend/README.md) — React 18 + TypeScript SPA (Vite).

## Getting started

1. Copy env files:
   ```
   cp backend/.env backend/.env.local
   cp .env.example .env
   ```
2. Generate the JWT key pair (required once):
   ```
   docker compose run --rm app php bin/console lexik:jwt:generate-keypair
   ```
3. Start everything:
   ```
   make up
   ```
4. Run migrations:
   ```
   make migrate
   ```

Then:

- Frontend (Vite dev server, HMR): `http://localhost:5173`
- Backend API: `http://localhost:8111`, docs at `http://localhost:8111/api/doc`
- MinIO console (S3-compatible object storage, for links/photos/files): `http://localhost:9001`
  (credentials from `.env`, default `pouch` / `pouch-dev-secret`)

See `make start` for the full list of available commands (backend, frontend, and combined).

## Layout

```
backend/    Symfony API — see backend/README.md
frontend/   React SPA — see frontend/README.md
docs/       project-wide docs (code style, ...)
Makefile    orchestrates both, wrapping docker compose
```

## Status

Following `docs/ROADMAP.md`. So far: auth (login/logout/refresh), category tree with
role-based ACL, and items — general files, URLs (async OpenGraph scrape + page text
snapshot) and photos (async thumbnail + OCR) — with TTL/trash/GC and signed download
links. The frontend has login and a first items browsing view (`/items`); most of the UI
(category navigation, uploads, notes, search, tags) still isn't built.
