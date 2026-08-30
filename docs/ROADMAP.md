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
- [ ] Symfony Messenger: transport (Doctrine), jeden handler jako wzorzec.
- [ ] URL: scraping OpenGraph (tytuł/opis/miniatura) + snapshot treści strony,
      asynchronicznie.
- [ ] Zdjęcie: upload + generowanie miniatur, OCR (asynchronicznie), tekst z OCR
      zapisany pod wyszukiwarkę.
- [ ] Frontend: podgląd zdjęć i URL-i (karta z miniaturą/tytułem/opisem).

**Testy kodowe:** test handlera scrapera (zamockowany HTTP), test generowania
miniatur, test OCR na przykładowym obrazku (jeśli wynik da się sensownie assertować).

**Test ręczny:** dodać prawdziwy URL i zobaczyć czy się scrapnie, wrzucić zrzut
ekranu z tekstem i sprawdzić że tekst trafia do wyszukiwarki.

---

## Część 5 — Item: Notatka

Najprostszy typ z całej czwórki — celowo na końcu, jako odpoczynek po Części 4.

**Zakres:**
- [ ] CRUD notatek (tekst/markdown), edycja po fakcie.
- [ ] Frontend: edytor + podgląd renderowanego markdownu.

**Testy kodowe:** functional testy CRUD notatki.

**Test ręczny:** napisać notatkę z formatowaniem markdown, sprawdzić że podgląd się
zgadza.

---

## Część 6 — Tagi, ulubione, wyszukiwarka

Spina wszystkie 4 typy w jedno.

**Zakres:**
- [ ] Encja `Tag` (M:N do Item), przypisywanie/filtrowanie.
- [ ] Ulubione + widok "ostatnio dodane".
- [ ] Indeks `tsvector` łączący: nazwę, tagi, treść notatki, tekst OCR, tytuł/opis
      OpenGraph — jedno zapytanie po wszystkim.

**Testy kodowe:** integration test wyszukiwarki — zapytanie trafiające przez każdy
z kanałów (nazwa/tag/notatka/OCR/OpenGraph) osobno.

**Test ręczny:** poszukać czegoś po fragmencie tekstu z OCR-owanego zrzutu ekranu i
po tagu, sprawdzić czy trafia.

---

## Część 7 — Klucze dostępu + rate limiting

**Zakres:**
- [ ] Klucz na kategorię (dziedziczony przez podkategorie) i osobno na pojedynczy
      item.
- [ ] Rate limiting na próby wpisania klucza (wzorem `LoginRateLimiter`).

**Testy kodowe:** test dziedziczenia klucza (podkategoria bez własnego → dziedziczy
z rodzica), test rate limitera.

**Test ręczny:** spróbować wejść do chronionej kategorii bez klucza / ze złym kluczem
kilka razy z rzędu i zobaczyć blokadę.

---

## Część 8 — Wersjonowanie plików

**Zakres:**
- [ ] Nadpisanie pliku nowszą wersją bez zmiany ID/adresu w drzewie, historia wersji.

**Testy kodowe:** integration test: upload → nadpisz → sprawdź że stara wersja nadal
dostępna z historii, a referencje do itemu się nie zmieniły.

**Test ręczny:** nadpisać plik, przejrzeć historię wersji w UI.

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
