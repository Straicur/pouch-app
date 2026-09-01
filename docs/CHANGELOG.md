# Changelog

Zakończone etapy pracy nad projektem — co zostało zrobione i (skrótowo) dlaczego. Bieżący/przyszły plan jest w [`ROADMAP.md`](ROADMAP.md); aktualny model domenowy (nie historia jego powstawania) w [`architecture.md`](engineering/architecture.md).

Wpisy są w kolejności chronologicznej. Numery "Część N" to tylko etykiety porządkujące tę listę — nie odwołuj się do nich w kodzie (patrz [`project-rules.md`](engineering/project-rules.md)).

## Część 1 — Fundament (CI + Storage)

CI (`make cs`/`make phpstan`/`make test-backend`/`npm run check`/`npm test` na push/PR), klient S3 do MinIO (Flysystem), streaming upload/download bez buforowania całego pliku w pamięci PHP.

## Część 2 — Kategorie + role/ACL

Encja `Category` (drzewo, self-referencing) z CRUD, role `ROLE_ADMIN`/`ROLE_USER`/`ROLE_GUEST` na `User.roles` z voterem per endpoint.

## Część 3 — Item: plik ogólny + cykl życia

Encja `Item` (typ plikowy), upload do S3, wykrywanie duplikatów po hashu treści. TTL (domyślnie 1 dzień, presety 1h/7d/30d, "trzymaj zawsze"), dwuetapowy cykl kosza (wygasły → kosz → trwałe usunięcie po 7 dniach, `ItemGarbageCollector`). Podpisane, czasowe URL-e do pobierania zamiast bezpośredniego dostępu do S3.

## Część 4 — Item: URL i Zdjęcie

Symfony Messenger (kolejka Doctrine) dla pracy asynchronicznej. URL: scraping OpenGraph (tytuł/opis/miniatura) + snapshot treści strony. Zdjęcie: miniatury + OCR, tekst z OCR pod wyszukiwarkę.

## Część 5 — Item: Notatka + i18n

Warstwa tłumaczeń (Symfony Translation backend, i18next frontend) — całość user-facing tekstu przez klucze, nie na sztywno. CRUD notatek (markdown) z edycją po fakcie.

## Część 6 — Tagi, ulubione, wyszukiwarka

Encja `Tag` (M:N do Item), ulubione, `tsvector` łączący nazwę/tagi/treść notatki/OCR/OpenGraph w jedno zapytanie wyszukiwania.

## Część 7 — Klucze dostępu + rate limiting

Klucz na kategorię (dziedziczony przez podkategorie) i osobno na pojedynczy item, plus rate limiting na próby wpisania klucza.

## Część 8 — Wersjonowanie plików

Nadpisanie pliku nowszą wersją bez zmiany id/adresu w drzewie, historia wersji z możliwością pobrania starszej.

## Część 9 — Publiczne czasowe linki + eksport kategorii

Publiczny, czasowy link do pojedynczego itemu (bez konta). "Pobierz całą kategorię" jako strumieniowany ZIP z zachowaniem struktury drzewa.

## Część 10 — Panel admina

Storage: podgląd zużycia per typ + globalne limity wagowe. GC dashboard z ręcznym "Run GC Now" + historią. Audit log (kto/kiedy/skąd podejrzał/pobrał/usunął/zmienił klucz). Lista wygasających w 24h + masowe przedłużenie. Eksport/backup całości jako ZIP.

## Część 11 — Frontend dla Części 7–10

UI dla wszystkiego z Części 7–10 (do tego momentu tylko API). Struktura widoków przełożona na zagnieżdżony routing pod `src/modules/` (`/user/*`, `/admin/*`, wspólna nawigacja przez `ProtectedRoute`). Catalyst UI (Tailwind v4 + Headless UI) jako baza layoutu bocznego paska. `zod` + `react-hook-form` dla formularzy. Naprawiony po drodze realny bug: `axiosBaseQuery` psuło upload plików (`FormData` serializowane do JSON zamiast multipart).

## Część 12 — Poprawki po code review Części 7–11

Sześć znalezisk: granty dostępu przechodzące między użytkownikami po wylogowaniu (naprawione po obu stronach — czyszczenie `sessionStorage` na login/logout + podpis grantu wiążący go z `userId`/wersją klucza), eksport ZIP/backup buforowany w pamięci karty zamiast realnie streamowany, reset klucza nie unieważniał starych grantów, `window.open()` po `await` ryzykował blokadę popupu, publiczne linki niedostępne dla notatek/URL-i, formularze tworzenia nie pozwalały ustawić cyklu życia itemu.

## Część 13 — Druga runda code review

Osiem znalezisk. Najważniejsze: `og:image` wciąż umożliwiał SSRF (nowy współdzielony `SafeUrlFetcher` — waliduje **każdy** hop przekierowania, nie tylko wejściowy URL, i przypina request do zweryfikowanego IP przeciw DNS rebindingowi), uwierzytelnianie cookie podatne na CSRF (`SameSite=Lax` + `OriginCheckListener`), eksport/backup po cichu pomijały treść chronioną kluczem, usunięcie kategorii mogło osierocić pliki w storage jeśli w koszu (jeszcze niewyczyszczonym) zostawał item. Plus: paginacja liczona przed ACL (wyciek liczby ukrytych elementów), błędny resolver względnych URL-i, HTTP-owe błędy scrapera traktowane jak sukces.

## Część 14 — Trzecia runda code review (regresje po Części 13)

Siedem znalezisk na commit z Części 13. Najważniejsze: przypięcie IP w `SafeUrlFetcher` nie działało z prawdziwym transportem (`resolve` musi być kluczowane samym hostem, nie `host:port` — cURL/Symfony inaczej niż się wydawało), nadpisanie pliku nie było atomowe (naprawione transakcją DB + kompensacyjnym sprzątaniem storage przy błędzie). Plus: `UrlResolver` nie obsługiwał `false` z `parse_url()` (crash na złośliwie sformatowanej stronie), paginacja ACL robiła pełne skany danych zamiast skalarnych zapytań, granty w query stringu (przeniesione na krótkotrwały opaque token zamiast surowej treści w URL-u).

## Część 15 — Symfony PasswordHasher zamiast ręcznego `password_hash()`

Trzy miejsca (`AuthService`, `AccessKeyHasher`, `AppFixtures`/testy) przełączone z ręcznego `password_hash()`/`password_verify()` na skonfigurowany, ale wcześniej nigdy niewołany `symfony/password-hasher`. Efekt uboczny: testy tworzące userów dostały tańszy koszt hashowania z `when@test`, mierzalnie szybszy cały zestaw testów.

## Część 16 — Wiele użytkowników / zarządzanie kontami

Nadrzędna encja **`Pouch`** — "system", do którego należy user i cała jego reszta danych (zamiast prostego `ownerId` na `Category`/`Item`).

- **Izolacja danych**: `CategoryService`/`ItemService` filtrują po pouchu bieżącego usera — cudzy zasób wygląda jak nieistniejący (404, nigdy 403). Docelowy mechanizm: **Doctrine SQLFilter** (`PouchFilter` + `PouchAware` interfejs-znacznik + `PouchFilterListener`, dwa hooki — `kernel.request` zawsze wyłącza filtr na starcie, `kernel.controller` włącza go z bieżącym pouchem) zamiast ręcznego scope'owania w każdym miejscu osobno. Wyłączony jawnie dla `/api/admin/*` i rodziny podpisanych linków (`item_download`/`item_thumbnail`/`item_version_download`/`item_public_view`) — te dwa przypadki mają świadomie działać cross-pouch. Po drodze znalezione i naprawione dwa poważne błędy: SQLFilter cache'owane po kształcie DQL (parametr filtra musi iść przez `SQLFilter::setParameter()`, nie zwykłą właściwość), i `Repository::find($id)` omija filtr przez identity mapę Doctrine (zamienione wszędzie na `findOneBy()`).
- **Panel admina: konta i pouche** — tylko admin dodaje konta (losowe hasło tymczasowe, zwrócone raz w odpowiedzi), zmiana roli/blokada/reset hasła/usunięcie, przegląd pouchy. Blokada konta działa natychmiast (nie tylko na nowym logowaniu) przez `AppUserChecker` na każdym uwierzytelnionym requeście.
- **Reszta panelu admina "per pouch"** — Storage/GC/Audit Log/Expiring/Backup dostały wspólny `?pouchId=` (pominięty = wszystkie pouche). Zarządzanie itemami/plikami per pouch (`GET/DELETE /api/admin/items`), `PouchSwitcher` w navbarze.
- **Samoobsługowe usunięcie konta** — `DELETE /api/account` (zwykłe konto: kasuje tylko login, dane w pouchu zostają) i `DELETE /api/account/pouch` (tylko `ROLE_ADMIN`: kasuje cały pouch razem z sobą, wszystkimi kontami, kategoriami i itemami — odmawia, jeśli w pouchu jest jeszcze inne konto, albo jeśli wywołujący jest jedynym adminem w systemie z innymi kontami gdzie indziej). Eksport danych użytkownika świadomie poza zakresem.

## Część 17 — Tagi jako pełnoprawny zasób + 4 usprawnienia wyszukiwarki

- **Tagi**: pełny CRUD (`TagController`, `TagVoter`) zamiast tylko odczytu, scope'owane per Pouch (`tag.pouch_id`, `Tag implements PouchAware`) zamiast globalnie unikalnej nazwy. Migracja backfilluje pouch z itemów tagu, a tag współdzielony między pouchami (normalny stan pod starym, globalnym modelem) rozdziela na osobny wiersz per pouch zamiast go tracić.
- **Deterministyczna kolejność listy** — `id DESC` jako drugi klucz sortowania obok `createdAt`/`rank`, żeby remisy na `TIMESTAMP(0)` nie tasowały wyników między odświeżeniami.
- **Wyszukiwarka**: `url` dołączone do indeksu, komunikat pustego wyniku dopasowany do kontekstu wyszukiwania, podświetlanie dopasowanego fragmentu (`ts_headline`, sentinel zamiast HTML — bez `dangerouslySetInnerHTML`, żeby zawartość itemu nie mogła wstrzyknąć markupu), i tolerancja literówek (fallback na `pg_trgm`, tylko gdy dokładne dopasowanie nic nie zwróci). Późniejsza runda code review złapała i naprawiła: fuzzy fallback bez pouch-scopingu (dokładne trafienie w cudzym pouchu wyłączało fallback dla własnego), i snippet liczony też dla dopasowań bez realnego tekstowego trafienia (tag-only/fuzzy).

## Część 18, punkty 1 i 4 — Domknięcie izolacji Pouch + testy bezpieczeństwa

Audyt (nie nowa funkcja) potwierdził, że `PouchFilter` z Części 16 już faktycznie obejmuje każdą operację na itemie/kluczu dostępu (aktualizacja, usuwanie, generowanie linków, historia wersji, wykrywanie duplikatów) — dopisane testy regresyjne dla obszarów, które wcześniej nie miały jawnego pokrycia (`AccessKeyPouchIsolationTest`, rozszerzenia `ItemPouchIsolationTest`). Osobno naprawiony realny gap: wyszukiwarka (`searchMatchingIds`/`searchMatchingIdsFuzzy`) to jedyne miejsce, gdzie `PouchFilter` nie sięga (surowe SQL poza DQL) — teraz jawnie scope'owane przez `?int $pouchId`.

Dodane testy bezpieczeństwa: SSRF przez wielohopowe przekierowanie (nie tylko pierwszy hop) i IPv4-mapped-IPv6/notacja dziesiętna jako literały IP, grant dostępu przypisany do innego konta w tym samym pouchu (nie działa), podmiana `id` w ważnym podpisanym linku pobierania (nie działa — sygnatura liczona po id), wyciek istnienia danych przez wyszukiwarkę cross-pouch.

## Część 18, punkt 2 — Uprościć kod i dokumentację

`ROADMAP.md` przycięty do wyłącznie przyszłej pracy; zakończone etapy przeniesione do tego pliku (`CHANGELOG.md`, nowy). `docs/engineering/architecture.md` dociągnięty o wszystko, co powstało po Części 6 (wcześniej opisywał tylko fundament): wielodostępność (Pouch, mechanizm `PouchFilter`), konta, access key + podpisane linki + ochrona CSRF, tagi, wyszukiwarkę, audit log, SSRF. Komentarze referencyjne do numeru części/rundy code review ("Część N", "Post-review fix") usunięte z ~50 plików w `backend/src`/`frontend/src`, zastąpione bezczasowym wyjaśnieniem "dlaczego" bez odwołań do historii powstania.

## Część 18, punkt 3 — Testy frontendu

Backend miał ponad 250 testów, frontend tylko 2 (`LoginPage.test.tsx`) — nowe testy komponentów (Vitest + Testing Library) pokrywają najbardziej wartościowe scenariusze użytkownika: otwieranie modalu szczegółów itemu i obsługa błędu API (z przyciskiem "Ponów"), odblokowywanie itemu kluczem dostępu (poprawny/błędny klucz), dodawanie pliku/notatki (`AddItemModal`, w tym walidacja braku pliku), filtry z debounce (`ItemFilters` — odróżnia debounce'owane wyszukiwanie od natychmiastowego przełącznika "tylko ulubione"), przełączanie ulubionych (mysz i klawiatura), usuwanie itemu z potwierdzeniem (`ConfirmDialog`), odświeżanie cache RTK Query po mutacji bez ręcznego przeładowania.

Nowe współdzielone narzędzia w `src/testUtils/` — `renderWithProviders()` (świeży per-test Redux store + `MemoryRouter` + `ToastContainer`), `mockApiResponse()`/`mockApiError()` mockujące `libs/httpMethods` (jedyne miejsce, przez które `axiosBaseQuery` faktycznie robi request — więc prawdziwe RTK Query slice'y, tagi i `invalidatesTags`/`onQueryStarted` działają jak w apce, mockowany jest tylko transport). Konwencja opisana w `FRONTEND.md`, "Testy komponentów".

Po drodze znaleziony i naprawiony realny bug: część `onQueryStarted` handlerów w `store/api/*.ts` nie łapała błędu z `queryFulfilled`, co przy nieudanej mutacji zostawiało nieobsłużone odrzucenie promise'a.

## Opcjonalne poprawki (poza główną kolejnością)

- Usunięcie kategorii z aktywnymi itemami w środku (także w koszu, jeszcze niewyczyszczonym) teraz blokowane (409), zamiast kasować je z pominięciem kosza i osierocać pliki w storage.
- `GET /api/items` spaginowane (`{items, total, page, pageSize}`) zamiast zwracać całą, nieograniczoną listę z pełną treścią każdego itemu.
- `UrlValidator` rozwiązuje DNS i odrzuca prywatne/loopback/link-local adresy (w tym `169.254.169.254`) — nie tylko formalna walidacja URL-a.
