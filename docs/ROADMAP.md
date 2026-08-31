# Pouch — plan pracy

Kolejność, w jakiej budujemy projekt. Każda część ma: co robimy, jak to testujemy
kodowo, i jak to sprawdzamy ręcznie (żeby nie kończyć części tylko na zielonym CI —
realny klik w appce też się liczy). Checkboxy do odznaczania na bieżąco.

Część kończy się, gdy: kod + testy kodowe + ręczne sprawdzenie z tej sekcji są zrobione
i `make cs`/`make phpstan`/`make test-backend`/`npm run check`/`npm test` przechodzą.

---

## Część 1 — Fundament (CI + Storage)

Zanim ruszy jakakolwiek domena — bez tego każda kolejna część robi się na krzywym
gruncie.

**Zakres:**
- [x] CI (GitHub Actions albo równoważnik) odpalające na push/PR to, co już mamy w
      Makefile: `make cs`, `make phpstan`, `make test-backend`, `npm run check`,
      `npm test`.
- [x] Klient S3 do MinIO po stronie backendu (Flysystem + adapter S3, albo
      bezpośrednio SDK) — realny upload/download/delete na bucket, nie tylko
      skonfigurowany, ale użyty.
- [x] Streaming upload i download (bez buforowania całego pliku w pamięci PHP).

**Testy kodowe:** integration test klienta storage — upload pliku testowego → sprawdź
że istnieje w buckecie → pobierz → sprawdź zawartość → usuń → sprawdź że zniknął.
✅ `backend/tests/Storage/StorageServiceTest.php`.

**Test ręczny:** wrzucić plik przez tymczasowy endpoint/komendę konsolową, zobaczyć
go w konsoli MinIO (`:9001`), pobrać z powrotem i porównać zawartość.
✅ zrobione ręcznie przez `app:storage:upload` / `:download` / `:delete` — patrz
`backend/README.md`.

---

## Część 2 — Kategorie + role/ACL

Szkielet drzewa i uprawnień, zanim pojawi się cokolwiek do trzymania w środku.

**Zakres:**
- [x] Encja `Category` (drzewo, self-referencing), CRUD: dodaj / zmień nazwę /
      przenieś / usuń.
- [x] Role `ROLE_ADMIN` / `ROLE_USER` / `ROLE_GUEST` na `User.roles`, voter/guard per
      endpoint.
- [x] Fixtures z jednym kontem na każdą rolę.

**Testy kodowe:** functional testy kontrolera kategorii (CRUD), testy macierzy
uprawnień (guest nie tworzy/nie usuwa, user tworzy, admin wszystko).
✅ `backend/tests/Controller/CategoryController/CategoryControllerTest.php` +
`CategoryPermissionsTest.php`.

**Test ręczny:** zalogować się jako user/guest z fixtures, sprawdzić w Swaggerze
(`/api/doc`) że guest dostaje 403 tam, gdzie powinien.
✅ zrobione ręcznie przez `/api/login` + `/api/categories` z fixtures (guest/user/
admin z `AppFixtures`) — guest: 200 na GET, 403 na POST/DELETE; user: 201 na POST,
403 na DELETE; `/api/doc.json` renderuje się bez błędu.

---

## Część 3 — Item: plik ogólny + cykl życia

Pierwszy typ itemu, ale od razu z pełnym TTL/koszem/podpisanymi linkami — to jest
mechanizm, z którego skorzystają wszystkie kolejne typy, więc lepiej zrobić go
porządnie raz.

**Zakres:**
- [x] Encja `Item` (typ: plik ogólny), upload do S3 z Części 1, walidacja
      typu/rozmiaru, wykrywanie duplikatów (hash zawartości).
- [x] TTL: domyślnie 1 dzień, przełącznik "trzymaj na zawsze", presety (1h/7d/30d),
      własna data.
- [x] Cron/scheduler: wygasłe TTL → kosz, kosz → trwałe usunięcie po 7 dniach.
- [x] Podpisane, czasowe URL-e do pobierania/streamu (nie bezpośredni dostęp do S3).

**Testy kodowe:** integration testy: upload→download przez podpisany URL, TTL→kosz
(z podstawionym/przesuniętym czasem), kosz→trwałe usunięcie, duplikat wykryty.
✅ `backend/tests/Controller/ItemController/ItemControllerTest.php` (CRUD, walidacja,
duplikaty), `ItemDownloadTest.php` (podpisany URL), `backend/tests/Item/
ItemGarbageCollectorTest.php` (TTL→kosz, kosz→usunięcie, z podstawionym `$now`),
`backend/tests/Security/SignedUrlServiceTest.php` (HMAC + wygasanie).

**Test ręczny:** wrzucić plik, pobrać go podpisanym linkiem, poczekać (albo ręcznie
odpalić `make phpstan`... nie — ręcznie odpalić komendę GC) i sprawdzić że wpadł do
kosza, potem że realnie zniknął po 7 dniach (test na skróconym TTL w dev).
✅ zrobione ręcznie: upload z 3-sekundowym TTL → `app:item:gc` przeniósł do kosza
(404 z API, ale plik nadal w MinIO) → `app:item:gc --retention-days=0` trwale usunął
(zniknął z DB i z MinIO). Duplikat tej samej treści → 409 ze wskazaniem istniejącego
itemu.

---

## Część 4 — Item: URL i Zdjęcie

Tu wchodzi Messenger (kolejka) — OCR i scraping OpenGraph nie mogą blokować requestu.

**Zakres:**
- [x] Symfony Messenger: transport (Doctrine), jeden handler jako wzorzec.
- [x] URL: scraping OpenGraph (tytuł/opis/miniatura) + snapshot treści strony,
      asynchronicznie.
- [x] Zdjęcie: upload + generowanie miniatur, OCR (asynchronicznie), tekst z OCR
      zapisany pod wyszukiwarkę.
- [x] Frontend: podgląd zdjęć i URL-i (karta z miniaturą/tytułem/opisem).

**Testy kodowe:** test handlera scrapera (zamockowany HTTP), test generowania
miniatur, test OCR na przykładowym obrazku (jeśli wynik da się sensownie assertować).
✅ `backend/tests/MessageHandler/ScrapeUrlMessageHandlerTest.php` (+ `OpenGraphScraperTest.php`),
`backend/tests/Item/ThumbnailServiceTest.php`, `backend/tests/Item/OcrServiceTest.php`,
`backend/tests/MessageHandler/ProcessPhotoMessageHandlerTest.php`.

**Test ręczny:** dodać prawdziwy URL i zobaczyć czy się scrapnie, wrzucić zrzut
ekranu z tekstem i sprawdzić że tekst trafia do wyszukiwarki.
✅ zrobione ręcznie: dodano `https://symfony.com/` → `app:messenger:consume` (worker
w `docker-compose.yml`) w kilka sekund uzupełnił tytuł/opis/miniaturę/snapshot
tekstu strony. Wrzucono wygenerowany zrzut z tekstem "Sekretne haslo: pouch2026" →
OCR poprawnie odczytał treść do `extractedText`. Karty URL/zdjęcia zweryfikowane
wizualnie w przeglądarce (login → `/items`) — miniatury, tytuł i opis renderują się
poprawnie.

---

## Część 5 — Item: Notatka + warstwa tłumaczeń (i18n)

Najprostszy typ z całej czwórki — celowo na końcu, jako odpoczynek po Części 4. Przy
okazji: zanim przybędzie więcej user-facing tekstu, warto przestać wpisywać go na
sztywno w kodzie.

**Zakres:**
- [x] Backend: paczka tłumaczeń (Symfony Translation), polskie stringi w
      `translations/*.pl.yaml` (osobne domeny: komunikaty walidacji, komunikaty
      wyjątków), serwis tłumaczeń wstrzykiwany wszędzie, gdzie dziś są sztywne
      stringi (wyjątki API, walidatory) — pobierane po kluczu, nie zwracane wprost.
- [x] Frontend: analogicznie — biblioteka i18n (i18next/react-i18next), jeden katalog
      polskich stringów, komponenty pobierają teksty przez `t('klucz')` zamiast
      wpisanych na sztywno w JSX.
- [x] CRUD notatek (tekst/markdown), edycja po fakcie.
- [x] Frontend: edytor + podgląd renderowanego markdownu.

**Testy kodowe:** test że serwis tłumaczeń zwraca oczekiwany polski tekst po kluczu
(backend), functional test że odpowiedź API faktycznie zawiera przetłumaczony (nie
sztywny angielski) komunikat, functional testy CRUD notatki.
✅ `backend/tests/ExceptionManagement/TranslationTest.php`, assercja w
`CategoryControllerTest` na przetłumaczony `detail`,
`backend/tests/Controller/ItemController/ItemCreateNoteControllerTest.php` (CRUD +
walidacja + macierz uprawnień notatek).

**Test ręczny:** wywołać endpoint kończący się błędem (np. 404 na nieistniejącej
kategorii) i sprawdzić że komunikat jest po polsku; napisać notatkę z formatowaniem
markdown, sprawdzić że podgląd się zgadza.
✅ zrobione ręcznie: 401/403/404/409/400 z rzeczywistych requestów — wszystkie po
polsku, w tym komunikaty z podstawionymi wartościami (rozszerzenie pliku, nazwa/ID
konfliktującego itemu). Notatka z nagłówkiem/pogrubieniem/listą dodana przez
`/items` → podgląd markdown poprawny → edycja "po fakcie" zapisana i odświeżona w
karcie (zweryfikowane zrzutem ekranu z prawdziwej przeglądarki).

---

## Część 6 — Tagi, ulubione, wyszukiwarka

Spina wszystkie 4 typy w jedno.

**Zakres:**
- [x] Encja `Tag` (M:N do Item), przypisywanie/filtrowanie.
- [x] Ulubione + widok "ostatnio dodane".
- [x] Indeks `tsvector` łączący: nazwę, tagi, treść notatki, tekst OCR, tytuł/opis
      OpenGraph — jedno zapytanie po wszystkim.

**Testy kodowe:** integration test wyszukiwarki — zapytanie trafiające przez każdy
z kanałów (nazwa/tag/notatka/OCR/OpenGraph) osobno.
✅ `backend/tests/Controller/ItemController/ItemSearchControllerTest.php` (po jednym
teście na kanał: nazwa/tag/notatka/OCR/OpenGraph, plus test łączenia `q` z filtrem
kategorii), `backend/tests/Controller/ItemController/ItemTagFavoriteControllerTest.php`
(CRUD tagów, normalizacja wielkości liter, limit 20 tagów, ulubione, filtrowanie po
tagu/ulubionych, macierz uprawnień gość/user).

**Test ręczny:** poszukać czegoś po fragmencie tekstu z OCR-owanego zrzutu ekranu i
po tagu, sprawdzić czy trafia.
✅ zweryfikowane w prawdziwej przeglądarce (Playwright): dodanie notatki → oznaczenie
jako ulubione (★) → przypisanie tagów ("demo", "part6") → wyszukanie po unikalnym
słowie z treści notatki (trafia) → filtr "Tylko ulubione" (trafia, zwraca tylko
oznaczony item). Kanały OCR i OpenGraph zweryfikowane w testach integracyjnych
(rzeczywisty tesseract / bezpośrednio wstawiony item z metadanymi OG, bez zależności
od sieci — patrz komentarz w teście).

---

## Część 7 — Klucze dostępu + rate limiting

**Zakres:**
- [x] Klucz na kategorię (dziedziczony przez podkategorie) i osobno na pojedynczy
      item.
- [x] Rate limiting na próby wpisania klucza (wzorem `LoginRateLimiter`).

**Testy kodowe:** test dziedziczenia klucza (podkategoria bez własnego → dziedziczy
z rodzica), test rate limitera.
✅ `backend/tests/Security/AccessKey/AccessKeyServiceTest.php` (dziedziczenie — wprost
i przez nieoznaczonego rodzica, brak klucza w łańcuchu, własny klucz nie dziedziczy),
`backend/tests/Security/AccessKeyRateLimiterTest.php` (limiter skonstruowany
bezpośrednio z małym limitem — `access_key` jest w `when@test` podbity do 1000/15min,
jak `login`, więc nie da się tego sensownie przetestować przez webClient),
`backend/tests/Controller/AccessKeyController/AccessKeyControllerTest.php`
(ustawienie/zmiana klucza, zły klucz → 401, dobry klucz → grant, brak grantu → 403,
dziedziczenie przez HTTP, niezależny klucz itemu, i regresja na komunikat 403 —
zablokowany item bez własnego klucza w zablokowanej kategorii musi zgłaszać
"kategoria", nie "item", zablokowany — błąd złapany właśnie w teście ręcznym niżej).
`make cs` / `make phpstan` / `make test-backend` — 123/123, 0 błędów.

**Test ręczny:** spróbować wejść do chronionej kategorii bez klucza / ze złym kluczem
kilka razy z rzędu i zobaczyć blokadę.
✅ zrobione przez realne żądania HTTP do `:8111` (`make up` + `make migrate` +
`make fixtures`): założono kategorię, ustawiono klucz, próba dodania itemu bez
odblokowania → 403 "Ta kategoria jest chroniona kluczem dostępu."; 5 złych prób klucza
→ 401 za każdym razem, 6. próba → 429 z `retryAfter` (limit dev: 5/15min); dobry klucz
→ grant; z grantem w nagłówku `X-Pouch-Access-Grants` dodanie itemu i jego odczyt
działają, bez nagłówka ten sam item znów 403 (stateless — nic nie jest pamiętane po
stronie serwera). Po drodze złapano i naprawiono realny błąd: item bez własnego klucza
w zablokowanej kategorii zgłaszał się jako "item.locked" zamiast "category.locked"
(mylące — nie ma czym odblokować itemu, bo to kategoria go blokuje) — poprawione w
`AccessKeyGuard::assertItemUnlocked()` i pokryte nowym testem wyżej. Frontend (modal
na klucz) jeszcze nie powstał.

---

## Część 8 — Wersjonowanie plików

**Zakres:**
- [x] Nadpisanie pliku nowszą wersją bez zmiany ID/adresu w drzewie, historia wersji.

**Testy kodowe:** integration test: upload → nadpisz → sprawdź że stara wersja nadal
dostępna z historii, a referencje do itemu się nie zmieniły.
✅ `backend/tests/Controller/ItemController/ItemVersioningTest.php` (id/URL bez zmian
po nadpisaniu, stara wersja w historii i realnie pobieralna przez podpisany link,
wiele nadpisań buduje uporządkowaną historię, nadpisanie itemu innego niż plik → 400),
rozszerzony `backend/tests/Item/ItemGarbageCollectorTest.php` (purge kasuje z
MinIO także storage każdej zarchiwizowanej wersji, nie tylko bieżący plik itemu —
inaczej wersje wyciekałyby w buckecie po realnym usunięciu itemu). `make cs` /
`make phpstan` / `make test-backend` — 128/128, 0 błędów.

**Test ręczny:** nadpisać plik, przejrzeć historię wersji w UI.
✅ zrobione przez realne żądania HTTP do `:8111`: upload pliku (`v1.txt`, 11 B) →
nadpisanie (`v2.txt`, 24 B) → `GET /api/items/{id}` dalej pod tym samym `id`, ale z
metadanymi v2 → `GET /api/items/{id}/versions` pokazuje wersję 1 (v1.txt, 11 B) →
podpisany link do wersji 1 realnie zwraca oryginalną treść "v1 content". UI (frontend)
do przeglądania historii jeszcze nie powstało — na razie tylko API/Swagger, tak jak
modal na klucz z Części 7.

---

## Część 9 — Publiczne czasowe linki + eksport kategorii

**Zakres:**
- [x] Publiczny, czasowy link do pojedynczego itemu (bez konta, np. 24h).
- [x] "Pobierz całą kategorię" jako strumieniowany ZIP z zachowaniem struktury.

**Testy kodowe:** test generowania/wygasania publicznego linku, test streamowanego
ZIP-a (struktura + zawartość).
✅ `backend/tests/Controller/ItemController/ItemPublicLinkTest.php` (generowanie
wymaga loginu, URL-e są naprawdę absolutne — nie ścieżki względne jak private
15-minutowe linki z Części 3/4 — podgląd/pobranie działają bez żadnego auth,
działa dla itemów bez pliku (notatka), zmanipulowany podpis → 403; wygasanie samo
w sobie pokryte już przez `SignedUrlServiceTest`, bo to ten sam mechanizm, tylko
dłuższy TTL). `backend/tests/Controller/CategoryController/CategoryExportTest.php`
(struktura + zawartość zachowane, puste podkategorie nadal jako foldery, zablokowana
kategoria pomija swoje itemy ale zostawia odblokowane rodzeństwo — Część 7 — kolizje
nazw w jednym folderze dostają unikalne nazwy). `make cs` / `make phpstan` /
`make test-backend` — 137/137, 0 błędów.

**Test ręczny:** wygenerować publiczny link, otworzyć go w prywatnym oknie bez
zalogowania, pobrać całą kategorię i sprawdzić strukturę archiwum.
✅ zrobione przez realne żądania HTTP do `:8111` (bez ciasteczka auth na etapie
otwierania linku — dokładnie jak "prywatne okno bez zalogowania"): wygenerowano
link publiczny do pliku → `viewUrl` i `downloadUrl` to prawdziwe pełne URL-e
(`http://localhost:8111/...`, nie ścieżki względne) → oba otwarte bez żadnego
ciasteczka zwróciły 200 z poprawną treścią/metadanymi. Eksport kategorii z
podkategorią (plik + notatka) → prawdziwy plik `.zip` (`Content-Type:
application/zip`, `Content-Disposition` z nazwą kategorii) → rozpakowany:
struktura folderów zgodna z drzewem kategorii, plik i notatka (`.md`) z poprawną
zawartością. Dane testowe posprzątane.

---

## Część 10 — Panel admina

**Zakres:**
- [x] Storage/limity: podgląd zużycia (per typ), globalne limity wagowe.
- [x] GC dashboard: podgląd automatycznego czyszczenia + ręczne "Run GC Now" + logi.
- [x] Audit log: kto/kiedy/skąd (IP) podejrzał/pobrał/usunął/zmienił klucz.
- [x] Lista wygasających w 24h + masowe przedłużenie.
- [x] Eksport/backup całości jako ZIP.

Przy okazji: "Dodawanie kategorii" i "usuwanie cudzych itemów z dowolnej kategorii"
(product doc, sekcja admina) nie wymagały nowego kodu — pierwsze działa od Części 2
(`ROLE_USER`+), drugie nigdy nie było ograniczone do właściciela (`ItemVoter::DELETE`).
"Reset klucza dostępu" (Część 7, świadomie odłożone) rozwiązany teraz:
`AccessKeyController::setCategoryKey()`/`setItemKey()` wymagają dowodu znajomości
aktualnego klucza, chyba że wywołujący ma `ROLE_ADMIN` — patrz `AccessKeyGuard::
isItemOwnKeyUnlocked()`.

**Testy kodowe:** functional testy każdego endpointu admina + test uprawnień (tylko
`ROLE_ADMIN`).
✅ `backend/tests/Controller/AdminController/AdminControllerTest.php` (401/403 na
każdym endpoincie, storage report + wymuszenie ustawionego limitu na kolejnym
uploadzie, odrzucenie limitu dla typu bez rozmiaru, ręczne GC + historia, wpis w
audit logu po podejrzeniu itemu, lista wygasających + masowe przedłużenie usuwa z tej
listy, backup zawiera każdą kategorię root). Nowy test w
`AccessKeyControllerTest.php` na admin-reset. `make cs` / `make phpstan` /
`make test-backend` — 146/146, 0 błędów.

**Test ręczny:** przejść całą ścieżkę jako admin — zobaczyć dashboard, ręcznie
odpalić GC, sprawdzić log audytu po wykonaniu jakiejś akcji.
✅ zrobione przez realne żądania HTTP do `:8111` jako `admin@pouch.test`: dashboard
storage (puste → po uploadzie pliku pokazuje realne zużycie per typ + domyślne
limity), podejrzenie itemu → ręczne "Run GC Now" (`POST /api/admin/gc/run`) → wpis w
historii (`GET /api/admin/gc/runs`) → **audit log realnie pokazuje wcześniejsze
podejrzenie**: `{"action":"view","resourceType":"item","resourceId":18,
"userEmail":"admin@pouch.test","ip":"172.23.0.1",...}`. Lista wygasających i backup
(prawdziwy `.zip` z poprawną strukturą) też zweryfikowane. Dane testowe posprzątane
(wpisy audit/GC-log zostawione — to celowo append-only historia, nie dane testowe do
kasowania).

**Frontend:** cały panel admina to jeszcze samo API — UI (dashboard, przyciski,
tabele) nie powstał, tak jak dla Części 7/8/9.

---

## Część 11 — Frontend dla Części 7–10

Backend dla Części 7–10 (klucze dostępu, wersjonowanie plików, publiczne linki +
eksport kategorii, panel admina) był gotowy tylko jako API — front był "z tyłu" o
cztery części. Ta część domyka UI dla wszystkich czterech naraz.

**Dogrywka — struktura widoków:** początkowo wszystko trafiło płasko do `pages/`/
`components/` (jeden `AdminPage.tsx` z pięcioma sekcjami w jednym pliku). Przełożone
na zagnieżdżony routing pod `src/modules/`:
- `/user` (`modules/user/UserLayout.tsx`, wspólna nawigacja) → `/user/items`
  (`modules/user/items/`, własny `components/`), `/user/categories`
  (`modules/user/categories/`, własny `components/`); `modules/user/shared/` na
  `AccessKeyPanel` używany przez oba moduły.
- `/admin` (`modules/admin/AdminLayout.tsx`, jedno sprawdzenie 403 dla całego
  obszaru zamiast per-sekcja) → osobny moduł/trasa na każdą część dawnego
  `AdminPage.tsx`: `/admin/storage`, `/admin/gc`, `/admin/audit-log`,
  `/admin/expiring`, `/admin/backup`.

`make check`/`tsc -b`/`npm test` — czysto po przeniesieniu.

**Dogrywka 2 — pomysły z `e-rezerwacja-frontend` (z listy przejrzanej wcześniej):**
- `ProtectedRoute` (`components/ProtectedRoute.tsx`) — jedno sprawdzenie sesji dla
  całego obszaru zamiast per-strona; wpięte w `UserLayout` i `AdminLayout` (dla
  `AdminLayout` zdejmuje realny błąd: bez sesji w ogóle backend zwracał `401`, nie
  `403`, więc dotychczasowe sprawdzenie "czy admin" po prostu tego nie łapało i
  renderowało pełną nawigację tak jakby użytkownik był zalogowany).
- `lib/httpMethods.ts` — cienkie `get/post/put/patch/del` nad `httpClient` (wzorem
  `libs/core/src/httpClients/methods.ts`); `FormData`→multipart fix z Części 11
  przeniesiony tu, więc obowiązuje też poza RTK Query.
- `zod` + `react-hook-form` (wzorem `LoginPage`) dla pozostałych formularzy:
  `NoteForm`, `FileUploadForm`, `UnlockItemForm`, `AccessKeyPanel` (dwa formularze w
  jednym komponencie).
- **Catalyst UI / Tailwind v4 / Headless UI** — `src/ui/catalyst/` (`sidebar.tsx`,
  `navbar.tsx`, `sidebar-layout.tsx`, `link.tsx`, `touch-target.tsx`), ręcznie
  przeniesione z `libs/catalyst-ui` w `e-rezerwacja-frontend` (samo Catalyst to kod,
  który się kopiuje i modyfikuje, nie paczka npm) i przycięte do tego, czego
  faktycznie potrzebuje `SidebarLayout` — bez customowego systemu kolorów/wariantów
  przycisków z tamtego projektu, bez reszty kitu (dialog, combobox, table…). Wpięte
  w `UserLayout`/`AdminLayout` jako nawigacja (chowany sidebar na desktop, drawer na
  mobile) — reszta strony (karty itemów, formularze, tabele admina) zostaje na
  dotychczasowym plain CSS, to nie jest pełna migracja. `#root`'owi dodane wyjście z
  wąskiej kolumny na pełny viewport specjalnie dla tego layoutu.
  **Nie zweryfikowane wizualnie w przeglądarce** (jak reszta frontendu w tej sesji —
  brak `/chrome`) — tylko `tsc -b`/biome/`npm test` czysto; realne wejście na
  `/user`/`/admin` i sprawdzenie jak wygląda sidebar zostaje do zrobienia.

**Zakres:**
- [x] Klucze dostępu (Część 7): odblokowanie kategorii/itemu kluczem, ustawienie/
      zmiana/usunięcie klucza — na `ItemCard` i na nowej stronie kategorii.
      Granty przechowywane w `sessionStorage` i doklejane automatycznie do każdego
      requestu (`X-Pouch-Access-Grants`, interceptor w `httpClient`).
- [x] Wersjonowanie plików (Część 8): historia wersji + nadpisanie nową wersją +
      pobranie starej wersji, na `ItemCard` (tylko typ `file`).
- [x] Publiczne linki + eksport (Część 9): przycisk "Udostępnij" generujący 24h
      link bez konta, przycisk "Pobierz jako ZIP" na nowej stronie kategorii.
- [x] Panel admina (Część 10): nowa strona `/admin` — zużycie storage + limity,
      ręczne "Run GC Now" + historia, dziennik zdarzeń, lista wygasających +
      masowe przedłużenie, backup całości jako ZIP.

**Przy okazji (prerequisity, których wcześniej w ogóle nie było we froncie):**
- Upload pliku (`POST /api/items/files`) — front miał dotąd tylko formularz notatek;
  bez tego nie było czego wersjonować/udostępniać/eksportować.
- Przycisk pobrania pliku na `ItemCard` — była tylko miniatura, nie było w ogóle
  sposobu na ściągnięcie pliku.
- Nowa strona `/categories` — `categoryApi`'s własny komentarz już wprost mówił "no
  tree navigation UI yet, that's its own future piece of work"; Część 7 (klucz
  kategorii) i Część 9 (eksport kategorii) potrzebowały jakiegoś miejsca na listę
  kategorii, więc to jest ten moment. Celowo płaska lista, nie drzewo — realne drzewo
  to osobna, większa robota.
- `lib/apiError.ts` — współdzielony typ na kształt błędu (`context.uuid` z
  `ExceptionUuidEnum`), do tej pory tylko sugerowany w `docs/codestyle/FRONTEND.md`
  jako "warto dodać", teraz realnie zrobiony i użyty (m.in. żeby rozróżnić
  "zły klucz"/"nie ma klucza"/"za dużo prób" bez zgadywania po treści `detail`).

**Ważna poprawka po drodze:** `axiosBaseQuery` domyślnie ustawiał
`Content-Type: application/json` na każdym requeście — dla payloadu `FormData`
(upload pliku) axios w tej sytuacji **serializuje FormData do JSON-a zamiast wysłać
multipart** (sprawdzone wprost w źródłach axios: `transformRequest` patrzy na
nagłówek, nie na typ payloadu). Zweryfikowane empirycznie przez Node-owy skrypt
uderzający w żywy backend: bez poprawki → 400 "Brak pliku lub przesłany plik jest
nieprawidłowy"; z jawnym wyczyszczeniem nagłówka (`Content-Type: undefined`, żeby
przeglądarka sama dopisała boundary) → 201, plik faktycznie zapisany. Poprawka w
`axiosBaseQuery` obsługuje to dla każdego endpointu przyjmującego plik (upload,
nadpisanie wersji), nie tylko dla nowych.

**Testy kodowe:** `npm run check` (biome) i `tsc -b` (przez `npm run build`) czysto,
istniejące testy (`npm test`) zielone. Nowych testów komponentów nie dopisano — poza
zakresem tej sesji frontendowej, patrz "Test ręczny" niżej.

**Test ręczny:** ⏳ **nie wykonany w prawdziwej przeglądarce** — użytkownik nie
zainstalował rozszerzenia Claude in Chrome w tej sesji, a inne narzędzie do
sterowania przeglądarką nie było dostępne. Zamiast tego zweryfikowane pośrednio:
`tsc`/biome czysto, kontrakt request/response każdego nowego wywołania API ręcznie
zestawiony pole-po-polu z backendowymi DTO (Części 7–10 już wcześniej przetestowane
end-to-end przez `curl`), i opisany wyżej Node-owy test uploadu FormData na żywym
backendzie. **Realne kliknięcie przez UI w przeglądarce (`http://localhost:5174`) —
klucz dostępu, historia wersji, udostępnianie, eksport ZIP, panel admina — zostaje do
zrobienia przy najbliższej okazji**, najlepiej z rozszerzeniem Claude in Chrome
włączonym (`/chrome`) albo ręcznie przez użytkownika.

---

## Część 12 — Poprawki po code review Części 7–11

Sześć znalezisk z code review (2 wysokie, 4 średnie), wszystkie naprawione:

- [x] **[Wysoki] Granty dostępu przechodziły między użytkownikami po
      wylogowaniu.** Dwie warstwy: (1) frontend — `authApi.ts`'s `login`/`logout`
      `onQueryStarted` teraz wołają `accessGrants.clear()` (nowa metoda w
      `lib/accessGrants.ts`), więc grant zdobyty przez jednego użytkownika nie
      przetrwa do sesji następnego zalogowanego w tej samej karcie. (2) backend,
      defense-in-depth — `AccessKeyResource` (`backend/src/Security/AccessKey/`)
      teraz podpisuje `…:v{accessKeyVersion}:u{userId}`, nie sam `…:{id}`;
      `AccessKeyService`/`AccessKeyGuard` wstrzykują `Security` i rozwiązują
      bieżącego użytkownika, więc nawet gdyby frontend czegoś nie wyczyścił, grant
      podpisany dla usera A nigdy nie zmatchuje requestu usera B (backend woła
      `$security->getUser()` przy każdym sprawdzeniu, nie tylko przy wystawieniu).
      Migracja `Version20260831180000.php` dodaje `access_key_version` na
      `category`/`item`. Test `AccessKeyControllerTest` zaktualizowany pod nowy
      format zasobu.
- [x] **[Wysoki] Eksport ZIP/backup nie był realnie streamowany po stronie
      przeglądarki.** `lib/downloadBlob.ts` → `lib/triggerDownload.ts`: zamiast
      `httpClient.get(..., {responseType:"blob"})` (cały plik buforowany w pamięci
      karty przed zapisem) — zwykła nawigacja (`window.location.assign(url)`).
      Backend i tak już streamuje (`StreamedResponse` w `CategoryController::
      export()`/`AdminController`'s backup) i ustawia `Content-Disposition:
      attachment`, więc przeglądarka sama zapisuje plik strumieniowo, bez
      przeładowania SPA. Użyte w `BackupModule.tsx` i `CategoryRow.tsx`.
- [x] **[Średni] Reset/zmiana klucza nie unieważniała wcześniej wystawionych
      grantów.** Rozwiązane tym samym `access_key_version` co Finding #1 —
      `setAccessKeyHash()` bumpuje wersję przy każdej zmianie (patrz `Category`/
      `Item`), więc grant podpisany na starą wersję przestaje pasować natychmiast,
      bez listy odwołań.
- [x] **[Średni] `window.open()` po `await` ryzykował zablokowanie jako popup.**
      `ItemCard.tsx`'s `DownloadButton` i `VersionHistory.tsx`'s `handleDownload`
      teraz otwierają pustą kartę synchronicznie w handlerze kliknięcia (to wciąż
      liczy się jako user gesture), a dopiero po rozwiązaniu requestu ustawiają jej
      `location.href` — zamiast wołać `window.open()` dopiero po `await`.
- [x] **[Średni] Publiczne linki niedostępne dla notatek/URL-i.** `ShareButton` w
      `ItemCard.tsx` przeniesiony poza warunek `file`/`photo` — renderuje się dla
      każdego typu itemu; `DownloadButton` zostaje ograniczony do `file`/`photo`
      (jedyne typy z realną zawartością do pobrania).
- [x] **[Średni] Formularze tworzenia nie pozwalały ustawić cyklu życia itemu.**
      Nowy współdzielony `modules/user/items/components/lifecycleFields.tsx`
      (schema zod + `<LifecycleFieldsInput>`) używany przez `FileUploadForm` i
      `NoteForm`: wybór "domyślnie (1 dzień)" / "przechowuj zawsze" / `1h`/`7d`/
      `30d` / własna data. `itemApi.ts`'s `CreateFileRequest`/`CreateNoteRequest`
      rozszerzone o `keepForever?`/`ttlPreset?`/`expiresAt?`, zgodnie z backendowym
      `ItemLifecycleOptions`/`TtlPreset`.

**Weryfikacja:** frontend — `tsc -p tsconfig.app.json --noEmit` i
`biome check --write src` czysto (57 plików, tylko jedno niepowiązane
pre-existing ostrzeżenie w `main.tsx`). `npm test`/`vitest` nie dało się odpalić
lokalnie (uprawnienia na `node_modules/.tmp`/`node_modules/.vite-temp` z
wcześniejszego uruchomienia w kontenerze jako root) — do zrobienia w kontenerze.
Backend — `make cs`/`make phpstan`/`make test-backend`/migracja **nie zostały
jeszcze uruchomione**: kontenery są zatrzymane (użytkownik wyłączył je wprost w tej
sesji) i nie było zgody na ich ponowne włączenie od tego momentu — kod przejrzany
ręcznie (w tym `php -l`-równoważny przegląd wszystkich zmienionych plików), ale
realna weryfikacja (testy, PHPStan, migracja) czeka na kontenery.

**Test ręczny:** ⏳ nie wykonany — te same ograniczenia co w Części 11 (brak
`/chrome`, kontenery zatrzymane).

---

## Część 13 — Druga runda code review

Osiem znalezisk (4 P1, 4 P2), wszystkie naprawione:

- [x] **[P1] Pobieranie `og:image` nadal umożliwiało SSRF.**
      `ScrapeUrlMessageHandler::tryDownloadThumbnail()` wołało
      `httpClient->request()` wprost, bez żadnej z zabezpieczeń, jakie strona
      dostała w Części 12. Nowy współdzielony `Item\Scraper\SafeUrlFetcher`
      (+ `SafeUrlFetcherInterface`) — jedno miejsce na "bezpieczny fetch
      dowolnego URL-a": wyłącza auto-podążanie za przekierowaniami
      (`max_redirects: 0`), samodzielnie idzie za `Location`, na **każdym**
      hopie woła `UrlValidator::assertValidAndPin()` (DNS + zakres IP) i
      **przypina request do zweryfikowanego IP** przez opcję `resolve`
      Symfony HttpClient — zamyka też lukę DNS rebindingu (czas między
      sprawdzeniem a realnym połączeniem). Używane teraz zarówno przez
      `OpenGraphScraper` (HTML), jak i `ScrapeUrlMessageHandler` (obrazek).
      Test: `ScrapeUrlMessageHandlerTest::testOgImagePointingAtAPrivateAddressIsSkippedNotFetched`.
- [x] **[P1] Uwierzytelnianie cookie było podatne na CSRF.** Dwie warstwy:
      (1) `CookieService` — `SameSite=None` → `SameSite=Lax` (frontend/API są
      same-origin, "site" w SameSite to domena, nie port, więc nic więcej nie
      trzeba zmieniać). (2) nowy `OriginCheckListener` — na każdym
      POST/PUT/PATCH/DELETE sprawdza nagłówek `Origin` (albo `Referer` jako
      fallback) przeciwko tej samej liście dozwolonych originów co
      nelmio/cors-bundle (`CORS_ALLOW_ORIGIN`), 403 jeśli się nie zgadza.
      Wyłączony w env testowym (`.env.test`, `CSRF_ORIGIN_CHECK_ENABLED=0`) —
      ~150 istniejących testów funkcjonalnych nie wysyła `Origin`, ten sam
      powód co bumpowanie rate limiterów tam. `OriginCheckListenerTest`
      sprawdza go bezpośrednio (bez pełnego kernela HTTP).
- [x] **[P1] Eksport i backup pomijały treść chronioną kluczem po cichu, bez
      komunikatu.** Dwa osobne problemy: (a) `triggerDownload()` (Część 12)
      to `window.location.assign()` — nawigacja nie może ustawić nagłówka
      `X-Pouch-Access-Grants`, więc granty zdobyte wcześniej w sesji nigdy
      nie docierały do backendu. Frontend teraz dokleja te same (już
      podpisane, już krótkoterminowe) granty jako parametr `?grants=`;
      `CategoryController::export()` przekłada go z powrotem na nagłówek,
      który czyta `AccessKeyGuard` (stała `GRANTS_HEADER` przeniesiona na
      `AccessKeyGuardInterface`, żeby kontroler nie zależał od
      implementacji). (b) pełny backup admina (`AdminController::backup()`)
      dostał jawną politykę: **zawsze zawiera wszystko**, bez wyjątków —
      `CategoryExportService::buildZip()`/`buildFullBackupZip()` mają teraz
      wewnętrzny `bypassLocks: bool` (`false`/`true` odpowiednio), ten sam
      wzorzec co bypass admina przy resecie klucza. Testy:
      `CategoryExportTest::testExportWithGrantViaQueryParamIncludesUnlockedContent`,
      `testAdminBackupIncludesLockedContentWithoutAnyGrant`.
- [x] **[P1] Usunięcie kategorii wciąż mogło osierocić pliki w storage.**
      `ItemRepository::existsActiveInCategories()` (Część 12) filtrował do
      `trashedAt IS NULL` — kategoria z samymi itemami w koszu (jeszcze nie
      wyczyszczonymi przez GC) przechodziła usunięcie, a kaskada kasowała je
      z bazy, zanim `ItemGarbageCollector::purgeTrash()` zdążył posprzątać
      ich obiekty w S3/MinIO. Metoda przemianowana na
      `existsInCategories()` i **nie filtruje już po `trashedAt` wcale** —
      blokuje usunięcie, dopóki w poddrzewie zostaje jakikolwiek item,
      niezależnie od stanu kosza. Test:
      `CategoryControllerTest::testDeletingCategoryWithOnlyATrashedUnpurgedItemStillFails`.
- [x] **[P2] Paginacja liczona przed ACL — puste strony, wyciek liczby
      ukrytych elementów.** `ItemRepository::findFilteredPage()` liczyło i
      stronicowało wszystkie pasujące itemy, a dopiero kontroler odsiewał te
      bez grantu — strona mogła wyjść pusta mimo dostępnych itemów na
      kolejnej, a `total` zdradzał istnienie zablokowanych danych. Nowe
      `AccessKeyGuard::lockedCategoryIds()`/`lockedItemIdsWithOwnKey()`
      (masowo, nie kategoria-po-kategorii) liczą, co jest zablokowane, **przed**
      zapytaniem — `ItemController::list()` przekazuje te listy do
      `findFilteredPage()`, które dokłada `NOT IN (...)` do `WHERE` przed
      `COUNT`/`OFFSET`/`LIMIT`. Post-fetch filtr w kontrolerze zostaje jako
      tania warstwa defense-in-depth, nie główny mechanizm. Testy: nowy
      `ItemListPaginationTest` (4 przypadki, w tym
      `testPageOneIsNotEmptyWhenTheNewestItemsAreLocked` — bezpośrednia
      regresja opisanego scenariusza).
- [x] **[P2] Pobieranie plików mogło kończyć się pustą kartą.** Poprawka z
      Części 12 (`window.open("", "_blank", "noreferrer")` + ustawienie
      `location.href` po `await`) była wadliwa — `noreferrer` implikuje
      `noopener`, a przy `noopener` `window.open()` **zawsze** zwraca `null`,
      więc `tab.location.href = ...` nigdy się nie wykonywało. Zamienione na
      nawigację w bieżącej karcie (`window.location.assign(link.url)`) —
      podpisany link ma `Content-Disposition: attachment`, więc pobieranie
      startuje bez opuszczania aplikacji, bez ryzyka blokady popupów, bez
      cichego no-opa. `ItemCard.tsx`'s `DownloadButton`,
      `VersionHistory.tsx`'s `handleDownload`.
- [x] **[P2] Niepoprawne rozwiązywanie względnych URL-i.** Stary resolver w
      `OpenGraphScraper` doklejał ścieżkę względną zawsze do originu —
      `og:image="cover.jpg"` na `https://example.com/articles/123/page.html`
      dawało `.../cover.jpg` zamiast `.../articles/123/cover.jpg`. Nowy
      `Item\UrlResolver` (statyczny, bezstanowy) implementuje uproszczony
      RFC 3986 §5.3 — używany zarówno do `og:image`, jak i do rozwiązywania
      `Location` przy przekierowaniach w `SafeUrlFetcher`. Test:
      `OpenGraphScraperTest::testResolvesADocumentRelativeImageUrlAgainstTheCurrentPath`.
- [x] **[P2] Obowiązkowe checki backendu nie przechodziły.** Dwa konkretne
      PHPStan błędy w `ItemRepository::findFilteredPage()`'s wyszukiwarkowej
      gałęzi naprawione: `array_map` nad `array_slice($orderedIds, ...)`
      zastąpione pętlą z jawnym `isset()` (offset już niepewny dla PHPStan
      przez ten łańcuch), co też usunęło zbędne opakowujące `array_values()`.
      `array_filter` w `UrlValidator::resolve()` ma teraz jawny, ścisły
      callback (był już tak napisany od Części 12, ale to dokładnie ten
      wzorzec, o który chodziło). Trzy pliki cs-fixera z listy
      (`OpenGraphScraper.php`, `CategoryServiceInterface.php`,
      `ItemRepository.php`) zostały w międzyczasie **całkowicie przepisane**
      w ramach napraw powyższych punktów — numery linii z CR już nie
      odpowiadają obecnej treści; ręczny przegląd formatowania każdego z nich
      nie znalazł oczywistych problemów, ale **realne potwierdzenie wymaga
      odpalenia `make cs`**, nie zostało zasymulowane.

**Weryfikacja:** frontend — `tsc -p tsconfig.app.json --noEmit` i
`biome check --write src` czysto po każdej rundzie zmian. Backend —
`make cs`/`make phpstan`/`make test-backend`/migracja **wciąż nie zostały
uruchomione**: kontenery zatrzymane, brak zgody na ponowne włączenie. Kod
przejrzany ręcznie (w tym każdy nowy/zmieniony plik pod kątem składni i
zgodności typów), nowe/zaktualizowane testy opisane przy każdym punkcie —
realna weryfikacja czeka na kontenery.

**Test ręczny:** ⏳ nie wykonany — te same ograniczenia co w Części 11/12.

---

## Część 14 — Trzecia runda code review (regresje po Części 13)

Siedem znalezisk (2 P1, 5 P2) na commit z Części 13 — kilka to realne regresje
wprowadzone przez tamte poprawki, nie nowe tematy. Wszystkie naprawione:

- [x] **[P1] Przypięcie IP w `SafeUrlFetcher` nie działało z prawdziwym
      transportem.** `'resolve' => [$pin['host'] . ':' . $pin['port'] => $pin['ip']]`
      — Symfony (i cURL pod spodem) oczekuje mapy kluczowanej **samym hostem**;
      port dokleja sam transport z URL-a requestu. Klucz `"host:port"` kończył
      się jako `host:port:port:ip` w `CURLOPT_RESOLVE` (potwierdzone w źródle
      `CurlHttpClient.php`: *"curl's resolve feature varies by host:port but
      ours varies by host only"*) — pinning realnie nie działał, luka DNS
      rebindingu zostawała otwarta, a testy tego nie łapały, bo
      `MockHttpClient` nie symuluje zachowania transportu. Naprawione na
      `[$pin['host'] => $pin['ip']]`; `UrlValidator::assertValidAndPin()` nie
      liczy już w ogóle portu (był tylko dla tego jednego, błędnego użycia).
      Test: nowy `SafeUrlFetcherTest::testResolveOptionIsKeyedByHostOnlyNotHostAndPort`
      (przechwytuje realne opcje przekazane do `HttpClientInterface::request()`).
- [ ] **[P1] Nadpisanie pliku nie było atomowe.** `ItemService::overwriteFile()`
      archiwizowało poprzednią wersję i flushowało ją do DB **przed** uploadem
      nowego pliku — nieudany upload zostawiał "wersję" bez faktycznej podmiany;
      nieudany finalny flush osierocał nowy plik w storage. Naprawione:
      upload najpierw (nic do sprzątania przy jego porażce), potem archiwizacja
      wersji + aktualizacja itemu w jednej transakcji DB
      (`Connection::transactional()`), z kompensacyjnym `storageService->delete()`
      nowego klucza, jeśli transakcja się nie powiedzie — ten sam wzorzec co
      istniejące już `saveNewItemOrConflict()`. **Bez automatycznego testu** —
      symulacja realnej awarii w połowie transakcji wymagałaby infrastruktury
      do wstrzykiwania błędów (fault injection), której ten projekt nigdzie
      indziej nie ma; kod przejrzany ręcznie, nie zweryfikowany testem.
- [x] **[P2] `UrlResolver` nie obsługiwał `false` z `parse_url()`.**
      `parse_url($reference, PHP_URL_PATH) ?? ''` — `??` łapie tylko `null`
      (brak komponentu), nie `false` (błędny URL); `false` leciało dalej do
      `str_starts_with()`/`mergePaths()` i wywalało `TypeError`. Ponieważ
      `$reference` to `og:image`/`Location` ze strony **kontrolowanej przez
      atakującego**, to nie był tylko problem typów — jedna źle sformatowana
      strona mogła wywalić cały scrape. Naprawione jawnym `is_string()` na
      każdym z trzech wywołań (`path`/`query`/`fragment`), tym samym wzorcem,
      jaki reszta klasy już stosowała dla `scheme`/`host`. Nowy `UrlResolverTest`
      (7 przypadków — wcześniej nie było żadnego dedykowanego testu tej klasy).
- [x] **[P2] Paginacja ACL robiła pełne skany danych przy każdym requestcie.**
      `AccessKeyGuard::lockedCategoryIds()`/`lockedItemIdsWithOwnKey()` (Część 13)
      hydrowały pełne encje (wszystkie kategorie; wszystkie itemy z własnym
      kluczem, **także skoszowane**) i parsowały nagłówek grantów od nowa przy
      każdym pojedynczym sprawdzeniu. Naprawa poprawności (ACL przed
      paginacją) została, ale teraz taniej: nowe `CategoryRepository::
      findAllForLockCheck()`/`ItemRepository::findAllWithOwnAccessKeyForLockCheck()`
      zwracają same skalary (id/parentId/accessKeyHash/accessKeyVersion),
      drugie dodatkowo filtruje `trashedAt IS NULL`; `AccessKeyGuard` dostał
      `WeakMap<Request, list<AccessGrant>>` jako cache parsowanych grantów
      (właściwość `readonly`, obiekt `WeakMap` pod nią wciąż mutowalny —
      standardowy wzorzec na cache w niemutowalnym poza tym serwisie; klucz
      po `Request` = nic nie przecieka między requestami, nie trzeba ręcznie
      czyścić).
- [x] **[P2] Granty w query stringu.** Zamiast przekładać same granty na
      `?grants=` (realna treść w historii przeglądarki i logach proxy, bez
      górnego ograniczenia rozmiaru), nowy endpoint `POST /api/categories/
      {id}/export-token` wymienia bieżące granty (zwykły AJAX, nagłówek
      działa normalnie) na nieprzezroczysty, krótkotrwały (60 s) token —
      `CategoryExportTokenResponseDTO`, podpisany przez `SignedUrlService`
      (resource embeduje hash grantów + id kategorii + usera, więc token nie
      da się użyć dla innej kategorii ani z podmienionymi grantami). Tylko
      *token* trafia do URL-a; `GET /api/categories/{id}/export?token=...`
      dekoduje/weryfikuje i podkłada granty pod nagłówek, jeśli wszystko się
      zgadza — po cichu ignorując token nieprawidłowy/wygasły/dla innej
      kategorii (ta sama tolerancja co brak grantu w ogóle). Frontend:
      `accessKeyApi.ts`'s `useGetCategoryExportTokenMutation`,
      `CategoryRow.tsx` woła go przed `triggerDownload()`, który wrócił do
      bycia zwykłą, generyczną nawigacją. Testy:
      `CategoryExportTest::testExportWithTokenFromGrantsIncludesUnlockedContent`,
      `testExportIgnoresATokenMintedForADifferentCategory`.
- [x] **[P2] Nieudany eksport mógł zostawiać archiwa w `/tmp`.**
      `CategoryExportService::buildZipFromRoots()` czyściło tylko itemowe pliki
      tymczasowe w `finally` — sam `$zipPath` (utworzony `tempnam()` na samym
      początku) nigdy nie był usuwany przy awarii `open()`/budowania archiwum/
      `close()`. Do tego oba kontrolery (`CategoryController::export()`,
      `AdminController::backup()`) usuwały archiwum dopiero na końcu
      *udanego* callbacku strumienia — błąd przed/w trakcie streamu (albo
      rozłączenie klienta) też zostawiał plik. Oba miejsca dostały `try/
      finally` gwarantujące `@unlink()` niezależnie od wyniku. Test:
      `CategoryExportTest::testFailedExportDoesNotLeaveATempFileBehind`
      (kasuje obiekt ze storage pod itemem, wymusza błąd, sprawdza katalog
      tymczasowy przed/po).
- [x] **[P2] Obowiązkowe quality gates.** Dwa konkretne, zweryfikowane
      ręcznie: `OriginCheckListener` — brakujące `#[Override]` na
      `getSubscribedEvents()` (dopisane) i klasa niebędąca `readonly` mimo że
      wszystkie jej pola już były (zmienione na `final readonly class`,
      zgodnie z dominującą konwencją w tym kodzie). Reszta zgłoszonych
      błędów PHPStan/plików Rectora nie została policzona 1:1 — kontenery
      wciąż zatrzymane, `make cs`/`make phpstan`/rector nie zostały odpalone
      w tej rundzie też.

**Weryfikacja:** frontend — `tsc -p tsconfig.app.json --noEmit` i
`biome check --write src` czysto. Backend — **wciąż bez `make cs`/
`make phpstan`/`make test-backend`**, kontenery zatrzymane przez całą tę
sesję. Ręczny przegląd tym razem poszedł głębiej niż zwykle na jednym punkcie
konkretnie (`resolve` w Symfony HttpClient) — sprawdzone bezpośrednio w
źródle `vendor/symfony/http-client/{HttpClientTrait,CurlHttpClient}.php`,
nie z pamięci, bo to jest dokładnie ten rodzaj błędu, który "wygląda dobrze"
bez uruchomienia.

---

## Część 15 — Symfony PasswordHasher zamiast ręcznego `password_hash()`

Nie z code review — pytanie użytkownika ("czy warto korzystać z filesystemu
Symfony zamiast czystego PHP" → rozszerzone na "co jeszcze"), które ujawniło
realną, nie tylko kosmetyczną, okazję: `symfony/password-hasher` był
bezpośrednią zależnością z **skonfigurowanym, ale nigdy niewołanym**
`password_hashers` w `security.yaml` (włącznie z tańszym kosztem w
`when@test` — też nigdy nie używanym). Trzy niezależne miejsca ręcznie
wołały `password_hash()`/`password_verify()` zamiast przez tę fabrykę:

- [x] **`AuthService::getUserByEmailAndPassword()`** — `password_verify()`
      wprost → `UserPasswordHasherInterface::isPasswordValid()`.
- [x] **`AccessKeyHasher`** (Część 7 — hash klucza dostępu, niezwiązany z
      żadnym `User`) — `password_hash()`/`password_verify()` wprost →
      `PasswordHasherFactoryInterface::getPasswordHasher('access_key')`, nowy
      nazwany hasher w `security.yaml` (osobny od tego dla `User`, bo
      `UserPasswordHasherInterface` wymaga `PasswordAuthenticatedUserInterface`,
      a klucz dostępu nim nie jest) — algorytm/koszt sterowany configiem,
      **nie** hardkodowanym `PASSWORD_BCRYPT` w kodzie.
- [x] **`App\Util\PasswordHasher`** (hardkodowany bcrypt, `cost => 15`,
      niezależnie od środowiska) — usunięty całkowicie. Używały go tylko
      `AppFixtures` i `tests/DatabaseMockManager`, oba przepisane na
      `UserPasswordHasherInterface::hashPassword()`. Efekt uboczny: testy
      tworzące userów (a robi to prawie każdy test w tym repo) dostały
      **za darmo** dużo tańszy koszt hashowania z `when@test`'s configu
      (`cost: 4` zamiast zakopanego wcześniej `15`) — realny, mierzalny
      zysk prędkości całego zestawu testów, nie tylko porządek w kodzie.

`security.yaml` dostał nowy wpis `access_key: 'auto'` (plus taniej w
`when@test`, tym samym wzorcem co istniejący wpis dla `User`).

**Weryfikacja:** ręczny przegląd (kontenery zatrzymane, jak w poprzednich
częściach) — `make cs`/`make phpstan`/`make test-backend` nie odpalone.
`AccessKeyServiceTest`'s `new AccessKeyHasher()` zaktualizowane pod nowy
konstruktor (stub `PasswordHasherFactoryInterface`, metoda testowana i tak
nigdy nie dotyka hashera). Żadna zmiana kontraktu publicznego
(`AccessKeyHasherInterface`, `AuthServiceInterface`) — tylko wnętrza.

---

## Opcjonalne do naprawy (niezależne od kolejności wyżej)

Nie blokują żadnej części — zrobić przy okazji, kiedy akurat dotykamy powiązanego
kodu, albo osobno gdy będzie chwila:

- [ ] **Cookie `secure: true` na sztywno** (`CookieService`) — działa dziś tylko
      dzięki wyjątkowi przeglądarek dla `http://localhost`. Do ogarnięcia przed
      pierwszym wdrożeniem pod realną domeną (HTTPS na reverse-proxy musi faktycznie
      działać end-to-end).
- [ ] **Brak prod stage dla backendu** — `backend/Dockerfile` ma tylko `base`+`dev`
      (frontend ma `prod` z nginx, backend nie). Potrzebne przed pierwszym realnym
      wdrożeniem, nie wcześniej.
- [x] ~~Usunięcie kategorii z itemami w środku kasuje je z bazy na twardo, z
      pominięciem kosza~~ — **naprawione.** `CategoryService::delete()` teraz woła
      nowy `ItemRepository::existsActiveInCategories()` na całe poddrzewo
      (`collectSubtreeIds()`, rekurencyjnie przez `Category::getChildren()`) i
      odrzuca usunięcie z 409 (`category.not_empty`), jeśli ono samo albo
      którakolwiek z podkategorii wciąż ma aktywny (nietrasz-owany) item —
      wariant (a) z listy. Testy: `testDeletingCategoryWithAnActiveItemFails`,
      `testDeletingCategoryWithAnActiveItemInADescendantFails`
      (`CategoryControllerTest`).
- [x] ~~`GET /api/items` zwraca całą treść każdego itemu, bez paginacji~~ —
      **naprawione.** Nowy `ItemRepository::findFilteredPage()` (DB-owy
      `LIMIT`/`OFFSET` poza wyszukiwaniem pełnotekstowym, gdzie ranking i tak
      musi zobaczyć wszystkie trafienia przed pocięciem na strony — patrz metody
      własny komentarz) + nowy `ItemSummaryResponseDTO` (bez `extractedText` —
      OCR/scraped-page text, nigdzie nie renderowany we froncie;
      `noteContent` zostaje, bo `ItemCard` faktycznie pokazuje pełną treść
      notatki w liście). `GET /api/items` odpowiada teraz kopertą
      `{items, total, page, pageSize}` (`ItemListResponseDTO`, domyślnie
      `pageSize=50`, cap `200`) zamiast gołej tablicy. Frontend: `itemApi.ts`'s
      `ItemListResult`, `ItemsPage.tsx` ma prosty pager (poprzednia/następna
      strona, reset do strony 1 przy zmianie filtrów). `findFiltered()`
      (nieopaginowany) zostaje bez zmian dla `CategoryExportService`, które
      faktycznie potrzebuje całego zbioru.
- [x] ~~`UrlValidator` świadomie nie chroni przed SSRF~~ — **naprawione.**
      `UrlValidator::assertValid()` teraz rozwiązuje DNS hosta (A + AAAA) i
      odrzuca go, jeśli którykolwiek adres nie jest publicznie routowalny
      (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` — loopback,
      RFC1918/ULA, link-local łącznie z `169.254.169.254`), literały IP też.
      `OpenGraphScraper` woła to samo sprawdzenie na **każdym** przekierowaniu
      (`max_redirects: 0` + ręczne podążanie za `Location`, walidacja przed
      każdym hopem), nie tylko na wejściowym URL-u — to był właściwy brak, nie
      samo sprawdzenie usera-podanego URL-a. Testy: `UrlValidatorTest` (literały
      IP, bez DNS — deterministyczne), `OpenGraphScraperTest`'s
      `testRejectsARedirectToAPrivateOrLinkLocalAddress`/
      `testFollowsARedirectToAnotherPublicHost`.

**Weryfikacja tych trzech:** kod przejrzany ręcznie, testy dopisane/zaktualizowane
(w tym istniejące `GET /api/items` asercje w `ItemControllerTest`/
`ItemSearchControllerTest`/`ItemTagFavoriteControllerTest` przełączone na nową
kopertę `{items, ...}`) — **`make cs`/`make phpstan`/`make test-backend` nie zostały
jeszcze uruchomione**, z tego samego powodu co Część 12: kontenery zatrzymane, brak
zgody na ponowne włączenie od tamtej pory. Frontend: `tsc --noEmit` i
`biome check --write` czysto.
