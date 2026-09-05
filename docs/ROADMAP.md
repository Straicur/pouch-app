# Pouch — plan pracy

Wyłącznie przyszła/bieżąca praca. Zakończone etapy są w [`CHANGELOG.md`](CHANGELOG.md); aktualny model domenowy (nie historia jego powstawania) w [`engineering/architecture.md`](engineering/architecture.md).

Punkt kończy się, gdy: kod + testy kodowe + (jeśli dotyczy) ręczne sprawdzenie są zrobione i `make cs`/`make phpstan`/`make test-backend`/`make lint`/`npm test` przechodzą.

---

## Etap stabilizacyjny (Część 18)

Zewnętrzny przegląd całego projektu ocenił fundamenty jako solidne jak na projekt hobbystyczny — największy problem to nie brak funkcji, tylko rozjazd między deklarowaną jakością a faktycznym domknięciem niektórych mechanizmów. Punkty 1 (izolacja Pouch), 2 (dokumentacja/kod), 3 (testy frontendu) i 4 (testy bezpieczeństwa) zrobione — patrz `CHANGELOG.md`. Zostają:

### Generować kontrakt frontendu z OpenAPI

Typy odpowiedzi, enumy i UUID-y błędów (`ExceptionUuid`/`ApiErrorBody` w `libs/apiError.ts` — patrz `FRONTEND.md`'s "Error handling", ostatni punkt, gdzie to już jest zapisane jako świadomy dług) są dziś częściowo przepisywane ręcznie z backendowego `ExceptionUuidEnum`. Coraz bardziej podatne na rozjazdy przy każdej zmianie backendu.

- [ ] Wygenerowany klient albo przynajmniej typy wprost z `nelmio/api-doc-bundle`'owego `/api/doc.json` — jeden kontrakt backend–frontend, mniej ręcznego utrzymania, wcześniejsze wykrycie breaking changes (np. w CI).

### Poprawić operacyjność

Przed realnym używaniem projektu do ważnych danych — backup bez regularnie sprawdzanego restore'u jest tylko nadzieją, nie zabezpieczeniem:

- [x] **Automatyczny backup PostgreSQL i MinIO oraz test odtwarzania — zrobione.**
      Wcześniej: tylko ręczny, na żądanie ZIP z `AdminController`/`CategoryExportService`
      (eksport app-level, odtworzony z encji) — nie prawdziwy dump bazy, nie obejmował
      np. userów/audit logów/tagów. Nowy `BackupServiceInterface`/`BackupService`: pełny
      `pg_dump` (format custom) + mirror całego bucketa MinIO (`StorageServiceInterface::
      listAllKeys()`, nowa metoda) do jednego, znaczonego czasem katalogu, plus retencja
      (domyślnie 14 ostatnich, `BACKUP_RETENTION_COUNT`). Nowy serwis `backup-scheduler`
      w Dockerze (ten sam wzorzec co `gc-scheduler`) — `app:backup:run` co 24h, pisze do
      nowego wolumenu `backup-data` (`/var/backups/pouch`, lokalny — S3/off-site to
      świadomie osobny, późniejszy krok, patrz punkt niżej).
      **Test odtwarzania**: `app:backup:restore-test` — restore ostatniego backupu do
      świeżej, tymczasowej bazy w tym samym kontenerze Postgres (`CREATE DATABASE`/
      `pg_restore`/porównanie liczby wierszy `pouch`/`user`/`category`/`item` z żywą bazą/
      `DROP DATABASE ... WITH (FORCE)`) — udowadnia, że backup faktycznie się odtwarza, nie
      tylko że plik istnieje. `BackupServiceTest` (2 testy, prawdziwy pg_dump/pg_restore/
      MinIO, nie mocki) uruchamia to jako część `make test-backend`, więc regresja w
      mechanizmie odtwarzania od teraz psuje testy, nie tylko ręczną kontrolę.
      Techniczne: `backend/Dockerfile` dostał PGDG apt repo + `postgresql-client-16`
      (dopasowana wersja klienta do serwera — domyślny klient Debiana to v17, którego
      dumpy niosą ustawienia sesji, jakich serwer v16 nie zna, np. `transaction_timeout`).
      `symfony/process` dodany jako bezpośrednia zależność (`composer.json`).
  - [ ] **Nieodłożone na później**: backup ląduje dziś tylko na lokalnym wolumenie
        Dockera — przeniesienie na zewnętrzny storage (S3-kompatybilny) przed pierwszym
        realnym wdrożeniem, żeby backup fizycznie nie leżał na tym samym hoście co dane.
- [ ] Health checks dla DB, MinIO i kolejki Messengera.
- [ ] Monitoring failed messages / dead-letter queue (Messenger).
- [ ] Retencja audit logów (dziś rosną bez limitu — patrz `AuditLogger`).
- [ ] Limity liczby requestów też poza logowaniem/access key (dziś rate limiting jest tylko na `LoginRateLimiter`/`AccessKeyRateLimiter`).
- [ ] Procedura rotacji kluczy JWT i sekretów (`JWT_PASSPHRASE`/`JWT_PRIVATE_TOKEN`/`JWT_PUBLIC_TOKEN` — patrz `BACKEND.md`'s "Pułapki tego projektu").

---

## Część 19 — Przegląd stanu projektu: co jeszcze brakuje

Kolejny zewnętrzny przegląd, po Części 18 — wniosek: projekt wyszedł z fazy "dużo rzeczy jest
fundamentalnie źle", jest teraz w fazie "kilka brakujących przepływów użytkownika i twarde
przygotowanie do produkcji". Poniższe znaleziska zweryfikowane w kodzie przed wpisaniem tutaj.

### Najważniejsze braki

- [x] **Frontend nie udostępniał wszystkich czterech typów itemów — zrobione.** Backend
      już miał `POST /api/items/photos`/`.../urls`, brakowało frontendu —
      `itemApi.ts` dostał `createPhoto`/`createUrl`, `AddItemModal.tsx`'s `ItemKind`
      rozszerzony o `"photo"`/`"url"` z własnymi polami formularza (`PhotoFields`/
      `UrlFields`). Upload zdjęcia ma `capture="environment"` na inpucie —
      podpowiada mobilnym przeglądarkom otwarcie aparatu wprost (desktop bez wsparcia po
      prostu to ignoruje, zwykły file picker). Ani `createPhoto`, ani `createUrl` nie
      przyjmują tagów przy tworzeniu (backendowe `ItemService::createPhoto()`/
      `createUrl()` też nie) — dodanie tagów zostaje osobnym krokiem po utworzeniu,
      przez `ItemDetailsModal`.
- [x] **Zarządzanie strukturą jest niepełne na froncie — zrobione.** Kategorie: backend już
      miał `rename()`/`move()`, brakowało UI — dodane `RenameCategoryForm.tsx`/
      `MoveCategoryForm.tsx` + przyciski w `CategoryRow.tsx`. Itemy: przenoszenie **nie
      istniało wcale** — nowy endpoint `PATCH /api/items/{id}/move`
      (`ItemService::move()`, `Item::setCategory()`) + `MoveItemButton` w
      `ItemDetailsModal.tsx`. Cel przenoszenia itemu ograniczony do własnego pouch przez
      `CategoryService::getById()` (pouch-scoped), tak jak reszta lookupów. Testy:
      `ItemControllerTest::testMoveItemToAnotherCategory`/`testMoveToMissingCategoryReturnsNotFound`,
      `ItemPouchIsolationTest::testMovingAnItemIntoAnotherPouchsCategoryReturnsNotFound`.
- [x] **Automatyczny GC nie był rzeczywiście zaplanowany — zrobione.** Nowy serwis
      `gc-scheduler` w `docker-compose.yml`/`docker-compose.dev.yml` (ten sam wzorzec co
      `messenger-worker`) — pętla `app:item:gc` co godzinę (najkrótszy preset TTL to 1h).
- [x] **Dokumentacja produktu i roadmapa miejscami sobie przeczyły — rozstrzygnięte.**
  - Domyślny TTL: `PRODUCT.md` zaktualizowany pod istniejące zachowanie kodu — domyślnie
    "trzymaj na zawsze", nie 1 dzień (bezpieczniejsze, dane nie znikają przez przypadek).
  - Zakładanie kont: `PRODUCT.md` zaktualizowany pod istniejący panel admina (`POST
    /api/admin/users`) jako rzeczywisty mechanizm, zamiast opisu "zakładane w bazie".
  - "Ostatnio dodane": uznane za wciąż aktualną część MVP — nowa `RecentPage`
    (`/user/recent`, wpis w sidebarze), na wzór już istniejącej `FavoritesPage`: lista
    itemów bez żadnych filtrów, backend i tak sortuje po `createdAt DESC` domyślnie.
    Usunięte z `ROADMAP.md`'s "Produktowo" (nie jest już przyszłym pomysłem).
- [x] **`ItemController` urósł do 1244 linii** — przekraczał limit 1000 linii z
      `project-rules.md`. Rozdzielony na 4 kontrolery wg operacji: `ItemController`
      (core CRUD: list/get/delete, 245 linii), `ItemCreateController` (createFile/
      createPhoto/createUrl/createNote, 377 linii), `ItemEditController` (updateNote/
      move/updateTags/markFavorite/unmarkFavorite/overwriteFile/versions, 343 linie),
      `ItemDeliveryController` (download/thumbnail/versionDownload/publicView + ich
      podpisane linki, 472 linie). Wspólna logika parsowania list rozdzielonych
      przecinkami (tagi, id kategorii) wydzielona do nowego
      `ParsesCommaSeparatedValuesTrait`, na wzór istniejącego `AuthorizesRequestsTrait`.
      `make cs`/`make phpstan`/`make rector`/`make test-backend` przechodzą czysto
      (283/283 testów). **Post-review poprawka**: `extractUploadedFile()`/`fileSize()`
      zostały przy pierwszym podziale skopiowane 1:1 do `ItemCreateController` i
      `ItemEditController` zamiast wydzielone tak samo jak logika comma-separated —
      wyłapane w code review, naprawione nowym `ExtractsUploadedFileTrait` (ten sam
      wzorzec).
- [x] **Testy frontendu były nadal dość wąskie** — było 7 plików / 18 testów (patrz Część
      18, punkt 3). Doszły `CategoryRow.test.tsx` (zmiana nazwy/przenoszenie kategorii,
      sukces i błąd), `VersionHistory.test.tsx` (pusta historia, lista wersji + pobranie
      konkretnej, nadpisanie nowym plikiem, błąd z treścią z backendu) i rozszerzony
      `ItemDetailsModal.test.tsx` (przenoszenie itemu — sukces/błąd/przycisk zablokowany
      przy niezmienionym celu, edycja tagów — sukces/błąd) i nowy `RecentPage.test.tsx`
      (patrz "Ostatnio dodane" wyżej). Teraz 10 plików / 34 testy, `make lint`/tsc/
      `make test-frontend` przechodzą czysto. Ekrany administracyjne i uprawnienia gościa
      (nie ma osobnego UI — publiczny link jest podpisanym URL-em bez własnego ekranu)
      zostają poza zakresem tej rundy.

### Sugerowana kolejność

Wszystko z "Najważniejsze braki" wyżej (URL/zdjęcie w UI, przenoszenie/zmiana nazwy,
automatyczny GC, "ostatnio dodane", ustalenie domyślnego TTL) jest już zrobione.
Zostało, przed pierwszym użyciem z ważnymi danymi:

- backup jako atomowy artefakt (`.partial` → rename po sukcesie — zrobione) plus test
  odtworzenia, który realnie weryfikuje pliki MinIO, nie tylko liczniki wierszy w bazie,
- obsługa błędu w `backup-scheduler` bez cichego 24h maskowania (zrobione — `|| exit 1`,
  `restart: unless-stopped` dogrywa restart),
- produkcyjny obraz backendu (patrz "Opcjonalne do naprawy" niżej).
---

## Opcjonalne do naprawy (niezależne od kolejności wyżej)

Nie blokują żadnej części — zrobić przy okazji, kiedy akurat dotykamy powiązanego kodu, albo osobno gdy będzie chwila:

- [ ] **Cookie `secure: true` na sztywno** (`CookieService`) — działa dziś tylko dzięki wyjątkowi przeglądarek dla `http://localhost`. Do ogarnięcia przed pierwszym wdrożeniem pod realną domeną (HTTPS na reverse-proxy musi faktycznie działać end-to-end).
- [x] **Prod stage dla backendu i `docker-compose.prod.yml` — zrobione.** `backend/Dockerfile`:
      `dev` (Xdebug, bind-mount ./backend) i `prod` (kod + `composer install --no-dev` wpieczone w
      obraz, bez Xdebug wcale, nie tylko wyłączony) to teraz osobne ścieżki od `base`; nowy
      `nginx-prod` stage kopiuje `public/` (w tym `assets:install`-owane assety
      NelmioApiDocBundle) z `prod` zamiast bind-mountować `./backend` z hosta.
      `docker-compose.prod.yml` (nakładka na bazowy plik, na wzór `.dev.yml`): wszystkie
      backendowe serwisy + `frontend` na `target: prod`, publicznie wystawiony tylko
      `frontend` (port 80) — `db`/`minio`/backendowy `nginx` (8111, `/api/doc`) na
      `127.0.0.1` (SSH tunel dla admina, nie internet; thumbnaile/pobieranie i tak idą przez
      podpisane linki backendu, nigdy bezpośrednio z MinIO). Osobne tagi obrazów
      (`pouch-app-php-fpm-prod`/`pouch-app-frontend-prod`) — bazowy plik taguje `dev`/`prod`
      tym samym `image:`, więc zbudowanie jednego nadpisywało lokalny cache drugiego.
      Zweryfikowane end-to-end (build + up całego stacku, nie tylko `config`): backend realnie
      wstaje, serwuje `/api/doc` z assetami, `SecurityHeadersListener`'owe nagłówki lecą nawet
      na 500, porty faktycznie bindują się tylko na `127.0.0.1`. **Zablokowane na realnym
      wdrożeniu do czasu ręcznego, jednorazowego setupu na serwerze** (nieodłożone dalej, tylko
      poza zakresem samej infry):
  - [x] **Monolog handler dla `prod` — zrobione.** Nowy `when@prod` w `monolog.yaml`:
        `fingers_crossed` (próg `error`, wyklucza 404/405) flushuje bufor requestu do
        `nested` — `stream` na `php://stderr`, nie plik (`docker logs`/journald i tak to
        łapią i trzymają, obraz prod nie ma żadnej rotacji pliku pod `var/log`). Plus
        `console` (kolorowane logi dla `bin/console` w prod, np. `migrate-prod` niżej) i
        `deprecation` (osobny stream). Zweryfikowane: wymuszony błąd w kernelu (`env=prod`)
        realnie wypisał `app.ERROR: ...` na stderr — wcześniej (puste `handlers:`) znikał
        bez śladu.
  - [x] **`make migrate-prod`/`jwt-keypair-prod`/`jwt-rotate-prod` — zrobione.** Nowa sekcja
        w `Makefile` (`PROD_COMPOSE`/`EXEC_APP_PROD`, na wzór istniejącego `COMPOSE`/
        `EXEC_APP`) — zakłada stack już odpalony przez `docker-compose.prod.yml`, nie
        startuje/zatrzymuje go. `jwt-keypair-prod` używa `--skip-if-exists` (jak dev,
        ale przez natywną flagę komendy zamiast `test -f` w Makefile). `jwt-rotate-prod`
        (nowe, nie miało dev-owego odpowiednika) używa `--overwrite -n` — celowo
        nieinteraktywne pod przyszłego crona; **unieważnia każdy wydany access/refresh
        token** (oba podpisane tym keypairem), więc każda zalogowana sesja loguje się
        od nowa przy najbliższym requeście — to punkt rotacji, nie efekt uboczny.
  - [ ] **Prod vault sekretów Symfony nigdy nie zainicjalizowany** — `config/secrets/dev/`
        jest, `config/secrets/prod/` nie istnieje. `JWT_PASSPHRASE`/`JWT_PRIVATE_TOKEN`/
        `JWT_PUBLIC_TOKEN` (patrz `lexik_jwt_authentication.yaml`) idą przez
        `secrets:generate-keys --env=prod` + `secrets:set ... --env=prod`, nie przez `.env`.
        Bez tego `app` 500-uje na każdym requeście (`EnvNotFoundException`) — teraz
        przynajmniej widoczne w logu (patrz punkt wyżej), wcześniej znikało bez śladu.
  - [ ] Cron dla `jwt-rotate-prod` (comiesięczna rotacja kluczy JWT) — świadomie osobny,
        późniejszy krok; komenda wyżej jest gotowa, samego harmonogramu jeszcze nie ma.
- [ ] **CSP i HSTS nagłówki** — `SecurityHeadersListener`/nginx dziś ustawiają tylko `X-Content-Type-Options`/`X-Frame-Options`/`Referrer-Policy` (środowiskowo-neutralne). `Content-Security-Policy` czeka na publiczny origin storage'u (`img-src` musi wskazywać na produkcyjny `STORAGE_ENDPOINT`, dziś nieznany), `Strict-Transport-Security` czeka na realny HTTPS na reverse-proxy — oba do dodania razem z wdrożeniem produkcyjnym.
- [ ] **Testy frontendu wciąż wąskie — 11 plików / 38 testów na 45 komponentów spoza `ui/catalyst`.**
  Panel admina ma dziś 0% pokrycia mimo destrukcyjnych akcji (usuwanie/blokowanie kont,
  ręczne odpalenie GC). Do domknięcia, per obszar:
  - Panel admina: `UsersPage`/`UserRow`/`CreateUserForm` (tworzenie/rola/blokada/reset
    hasła/usunięcie konta), `StoragePage`, `GcPage` (ręczny "Run GC Now"), `BackupPage`,
    `AuditLogPage`, `ExpiringPage`, `AdminItemBrowser`, `PouchSwitcher`.
  - Formularze kategorii/tagów: `CategoryForm`, `RenameCategoryForm`, `MoveCategoryForm`,
    `TagForm`, `TagRow`.
  - Współdzielone: `ConfirmDialog` (używany wszędzie do potwierdzania usunięcia — błąd tu
    ma najszerszy promień rażenia), `AppSidebar`, `ThemeSwitch`, `ProtectedRoute`,
    `ErrorBoundary`, `AccessKeyPanel`, `TagsInput`, `ShareButton`.
  - Strony bez testów: `FavoritesPage`, `SettingsPage`, `CategoriesPage`, `HomePage`.
  - `AddItemModal.test.tsx` pokrywa dziś tylko część `ItemKind`-ów — dopisać brakujące
    warianty formularza (zdjęcie/URL/notatka) jeśli nie są objęte.

---

## Produktowo (pomysły na kolejne funkcje, po etapie stabilizacyjnym)

- [ ] Szybkie dodawanie materiałów z telefonu — PWA/share target.
- [ ] Import z przeglądarki / rozszerzenie "zapisz do Pouch".
- [ ] Zapisane wyszukiwania lub inteligentne kolekcje.
- [ ] Eksport i pełna przenośność danych (rozszerzenie eksportu kategorii na cały pouch).

**Nie blokuje** niczego z etapu stabilizacyjnego — to osobny, późniejszy wątek.

### Zrobione: kosz z ręcznym przywracaniem

Wcześniej trash → GC było jednokierunkowe do momentu `purgeTrash()` (7 dni), bez UI do
"przywróć z kosza" przed tym. Backend: `Item::untrash()`, `ItemRepository::findTrashedPage()`,
`ItemService::listTrashedPage()`/`getTrashedById()`/`restore()` (zawsze wraca z
`keepForever = true`, żeby nie trafić z powrotem do kosza przy najbliższym przebiegu GC),
nowe akcje w `ItemController`: `GET /api/items/trash` i `PATCH /api/items/{id}/restore`
(ten sam wzorzec auth→voter→AccessKeyGuard co reszta kontrolera; `restore()` używa
`ItemVoter::DELETE`, jak `delete()`). `AuditLoggerInterface::ACTION_RESTORE` — nowy wpis
w dzienniku zdarzeń. Frontend: `TrashPage`/`TrashItemRow` (płaska tabela, nie
`ItemGrid`/`ItemCard` — `GET /api/items/{id}` 404uje na skasowanym itemie, więc
`ItemDetailsModal` tu nie działa), wpis w sidebarze. Testy: backendowe
(`ItemControllerTest`: restore/trash-list, `ItemPouchIsolationTest`: izolacja obu nowych
endpointów) i frontendowe (`TrashPage.test.tsx`, 4 testy). Pełna bramka (`make cs`/
`phpstan`/`rector`/`test-backend` — 289/289, `make lint`/tsc/`test-frontend` — 38/38)
przechodzi czysto.
