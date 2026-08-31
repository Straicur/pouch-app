# CLAUDE.md

Ten plik zawiera wytyczne dla Claude Code podczas pracy z kodem w tym repozytorium.

Zawsze rozpocznij od @docs/README.md i przeczytaj wskazane tam dokumenty właściwe dla wykonywanego zadania.

## Zasady projektu

Zawsze przestrzegaj zasad z @docs/engineering/project-rules.md

## Dostęp do bazy danych i API (lokalnie)

- Baza danych to PostgreSQL w kontenerze `pouch_db` — nie twórz nowych userów/kontenerów, tylko połącz się z istniejącym przez `make db-cli` (albo `docker exec pouch_db psql ...`).
- Dane do połączenia (host, user, hasło, nazwa bazy) znajdują się w `backend/.env.local` w zmiennej `DATABASE_URL`. Odczytaj ją na świeżo przed połączeniem, nie zakładaj wartości z pamięci.
- Backend domyślnie dostępny lokalnie pod `http://localhost:8111` (docs: `http://localhost:8111/api/doc`), frontend (Vite dev, HMR) pod `http://localhost:5174`, konsola MinIO pod `http://localhost:9001`.
- Do testów API wymagana jest autoryzacja JWT. Sprawdź aktualny mechanizm logowania w `backend/config/routes/security.yaml` i `backend/src/Controller/Api` (login czy access key) zamiast zakładać nazwę endpointu z pamięci.

## Docker / komendy

- Stos uruchamiaj przez `make up` / `make start` (nie odpalaj `docker compose` bezpośrednio bez potrzeby) — pełna lista poleceń w `make help`.
- Backend: `make console <cmd>`, `make phpstan`, `make test-backend`, `make cs` / `make cs-fix`, `make rector` / `make rector-fix`.
- Frontend: `make npm <cmd>`, `make lint` / `make lint-fix`, `make test-frontend`.
