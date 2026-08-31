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
- [ ] Publiczny, czasowy link do pojedynczego itemu (bez konta, np. 24h).
- [ ] "Pobierz całą kategorię" jako strumieniowany ZIP z zachowaniem struktury.

**Testy kodowe:** test generowania/wygasania publicznego linku, test streamowanego
ZIP-a (struktura + zawartość).

**Test ręczny:** wygenerować publiczny link, otworzyć go w prywatnym oknie bez
zalogowania, pobrać całą kategorię i sprawdzić strukturę archiwum.

---

## Część 10 — Panel admina

**Zakres:**
- [ ] Storage/limity: podgląd zużycia (per typ), globalne limity wagowe.
- [ ] GC dashboard: podgląd automatycznego czyszczenia + ręczne "Run GC Now" + logi.
- [ ] Audit log: kto/kiedy/skąd (IP) podejrzał/pobrał/usunął/zmienił klucz.
- [ ] Lista wygasających w 24h + masowe przedłużenie.
- [ ] Eksport/backup całości jako ZIP.

**Testy kodowe:** functional testy każdego endpointu admina + test uprawnień (tylko
`ROLE_ADMIN`).

**Test ręczny:** przejść całą ścieżkę jako admin — zobaczyć dashboard, ręcznie
odpalić GC, sprawdzić log audytu po wykonaniu jakiejś akcji.

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
- [ ] **Usunięcie kategorii z itemami w środku kasuje je z bazy na twardo, z
      pominięciem kosza** — `CategoryService::delete()` po prostu usuwa rekord i
      liczy na `ON DELETE CASCADE`; `ItemGarbageCollector::purgeTrash()` (jedyne
      miejsce, które faktycznie kasuje pliki z S3/MinIO) nigdy nie dostaje szansy
      zobaczyć te itemy — ich storage key przepada bezpowrotnie, plik zostaje
      osierocony w buckecie. Do wyboru: (a) zablokować usuwanie kategorii, dopóki
      są w niej aktywne itemy (najprostsze), albo (b) przed usunięciem kategorii
      rekurencyjnie otrasz-ować wszystkie itemy w niej i jej podkategoriach, a
      dopiero po realnym opróżnieniu (przez GC albo synchronicznie) skasować samą
      kategorię. Wymaga świadomej decyzji UX, nie tylko poprawki kodu — stąd na
      liście do zrobienia, a nie zrobione od razu.
- [ ] **`GET /api/items` zwraca całą treść każdego itemu, bez paginacji** —
      `ItemRepository::findActive()` ładuje wszystkie aktywne itemy naraz, a
      `ItemMapper` dokleja do każdego pełny `extractedText`/`noteContent`. Przy
      większej kolekcji jeden request to potencjalnie kilka-kilkanaście MB JSON-a —
      kłóci się z założeniem mobile-first. Potrzebny osobny DTO "podsumowania" (bez
      pełnej treści) + paginacja na liście; pełna treść tylko na endpoincie
      szczegółów pojedynczego itemu. Naturalnie pasuje do Części 6 (wyszukiwarka i
      tak potrzebuje paginacji).
- [ ] **`UrlValidator` świadomie nie chroni przed SSRF** (prywatne/localne IP,
      metadata endpoints w chmurze) — udokumentowana decyzja z Części 3 ("self-hosted,
      single/few-user, pełne hardening zostawione poza MVP"), nie przeoczenie.
      Do rewizji, jeśli/gdy Pouch ma być kiedyś wystawiony publicznie z rejestracją
      dla wielu niezaufanych użytkowników — wtedy dorobić rozwiązywanie DNS i
      blokadę adresów prywatnych/loopback/link-local na każdym przekierowaniu, nie
      tylko na wejściowym URL-u.
