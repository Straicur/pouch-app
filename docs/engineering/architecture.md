# Architektura i decyzje domenowe

Ten dokument opisuje *dlaczego* backend jest zbudowany tak, jak jest — decyzje, których nie widać wprost z jednego pliku, tylko ze współpracy kilku serwisów. Konwencje kodu (styl, struktura katalogów) opisuje [`codestyle/BACKEND.md`](../codestyle/BACKEND.md); to tutaj jest miejsce na model domenowy.

## Item i Category

- Item należy zawsze do dokładnie jednej kategorii — świadomie jeden model, nie tagi + kategorie jako dwa niezależne sposoby grupowania (patrz `docs/PRODUCT.md`, sekcja "Model pojęciowy").
- Trzy typy itemów: plik (`FILE`), zdjęcie (`PHOTO`), URL. Tworzenie pliku/zdjęcia zapisuje treść w MinIO od razu; tworzenie URL-a tylko rejestruje wpis i **planuje pracę asynchroniczną** (patrz "Kolejka Messengera" niżej) — request nigdy nie czeka na scraping/OCR/miniaturkę.

## TTL, trash, garbage collection

- Item może mieć TTL (`App\Enum\TtlPreset`: `1h`/`1d`/`7d`/`30d`) — po przekroczeniu trafia do kosza (`trash()`), nie jest od razu kasowany.
- Item w koszu jest usuwany trwale (razem z plikiem w MinIO) dopiero po dalszych 7 dniach (`ItemGarbageCollector::DEFAULT_RETENTION`) — dwuetapowo, żeby przypadkowe/przeterminowane skasowanie dawało jeszcze okno na cofnięcie.
- `ItemGarbageCollector::run()` robi oba kroki na raz (`expireOverdueItems()` + `purgeTrash()`) i zapisuje `GcRunLog` — każde uruchomienie GC (komenda/cron) jest więc audytowalne, nie tylko "coś się skasowało w tle".

## Kolejka Messengera (Symfony Messenger)

- Handlery i ich message'y mieszkają razem w `src/Messenger/` (para plików, ten sam namespace — patrz `BACKEND.md`, sekcja "Struktura katalogów").
- `ScrapeUrlMessage` → `ScrapeUrlMessageHandler`: dla itemu typu URL pobiera metadane OpenGraph + snapshot tekstu strony, i best-effort ściąga/skaluje obrazek OG jako miniaturkę. Limituje rozmiar pobieranego obrazka (`MAX_THUMBNAIL_DOWNLOAD_BYTES`, 15 MB) — ochrona przed złośliwym/zepsutym URL-em OG, który strumieniowałby nieograniczone dane na workera.
- `ProcessPhotoMessage` → `ProcessPhotoMessageHandler`: dla itemu typu zdjęcie generuje miniaturkę i uruchamia OCR na oryginale.
- Oba handlery na starcie sprawdzają, czy item wciąż istnieje i nie jest w koszu (`isTrashed()`) — item mógł zniknąć/zostać skasowany, zanim worker zdążył go przetworzyć; handler wtedy po prostu nic nie robi, zamiast rzucać błąd.

## Wielodostępność: Pouch

- Nadrzędna encja **`Pouch`** — "system", do którego należy `User` i cała jego reszta danych (`Category`, przez nią pośrednio `Item`; `Item` ma też własną, denormalizowaną kolumnę `pouch_id` — nigdy nie zmienia kategorii po utworzeniu, więc synchronizacja jest jednorazowa). `Tag` też należy do jednego pouch (unikalna nazwa jest per-pouch, nie globalnie).
- Izolacja jest automatyczna, nie ręczna: encje należące do pouch implementują znacznik `App\Services\Pouch\PouchAware`, a Doctrine SQLFilter `PouchFilter` (`config/packages/doctrine.yaml`, domyślnie wyłączony) dokleja `WHERE pouch_id = :currentPouch` do **każdego** zapytania DQL/ORM na takiej encji — serwis nie musi pamiętać o scope'owaniu, bo nie da się o tym zapomnieć. `PouchFilterListener` włącza filtr na `kernel.controller` (dopiero tam JWT jest rozwiązany) z pouchem bieżącego usera, i bezwarunkowo wyłącza go na starcie **każdego** requestu (`kernel.request`, wysoki priorytet) — inaczej stan włączony dla poprzedniego usera/requestu mógłby przeciekać do kolejnego, niepowiązanego zapytania.
- Cudzy zasób (id z innego pouch) wygląda dokładnie jak nieistniejący — zawsze 404, nigdy 403. Do lookupu po id używać `findOneBy(['id' => $id])`, nie `find($id)` — `find()` sprawdza identity mapę Doctrine **przed** jakimkolwiek SQL i pomija filtr, jeśli ten id był już załadowany gdziekolwiek wcześniej w tym samym requeście.
- Filtr jawnie wyłączony (dopasowanie ścieżki/nazwy trasy w `PouchFilterListener`, nie ręczny suspend/restore w każdej akcji) dla `/api/admin/*` (panel admina jest z natury cross-pouch — każdy endpoint bierze `?pouchId=` jawnie, `null` = wszystkie pouche naraz) i dla rodziny podpisanych linków (`item_download`/`item_thumbnail`/`item_version_download`/`item_public_view` — własny, niezależny kanał autoryzacji, patrz niżej).
- Filtr działa tylko na zapytaniach ORM/DQL — **nie** na surowym SQL. Jedyne miejsce w kodzie, które faktycznie schodzi do raw SQL na danych scope'owanych per pouch, to wyszukiwarka pełnotekstowa (patrz "Wyszukiwarka" niżej) — tam pouch trzeba przekazać jawnie.
- Worker Messengera i GC (`ItemGarbageCollector`) nie mają "bieżącego requestu" ani sesji, więc filtr nigdy nie jest dla nich włączony — działają celowo na wszystkich pouchach naraz.

## Konta

- Konta (`User`) zakłada wyłącznie admin (`POST /api/admin/users`) — nie ma samodzielnej rejestracji (patrz `docs/PRODUCT.md`). Zamiast linku do ustawienia hasła (brak infrastruktury mailowej) serwer generuje losowe hasło tymczasowe i zwraca je **raz**, w odpowiedzi — admin przekazuje je dalej poza aplikacją.
- Blokada konta (`User::enabled`) działa natychmiast, nie tylko przy kolejnym logowaniu — `AppUserChecker` (wpięty jako `user_checker` firewalla) sprawdza to przy **każdym** uwierzytelnionym requeście, więc token wydany przed zablokowaniem przestaje działać na swoim najbliższym odświeżeniu, nie dopiero po naturalnym wygaśnięciu.
- Samoobsługowe usunięcie: `DELETE /api/account` (zwykłe konto) kasuje tylko login — pouch i dane w nim zostają nietknięte. Konto z `ROLE_ADMIN` nie może tego użyć (pouch admina zawsze ma co najmniej jedno konto, więc "zostaw dane, usuń tylko login" nie ma sensu) — ma zamiast tego `DELETE /api/account/pouch`, które kasuje cały pouch razem z sobą: wszystkie konta w nim, kategorie, itemy i ich pliki w storage. Odmawia, jeśli w pouchu jest jeszcze inne konto (usuń/przenieś najpierw w panelu admina), albo jeśli wywołujący jest jedynym adminem w systemie, a gdzie indziej istnieją inne konta, które zostałyby bez żadnego admina.

## Autoryzacja: access key + podpisane linki

- Niezależnie od kont istnieje **access key** — mechanizm blokowania pojedynczej kategorii/itemu kluczem (hasłem), sprawdzany przez `AccessKeyGuard`/`AccessKeyService` (`src/Security/AccessKey/`). Poprawne podanie klucza wystawia grant ważny 24h (`AccessKeyService::GRANT_TTL_SECONDS`) — świadomie dużo dłużej niż podpisane linki do pobrania (900s, patrz niżej), bo wpisywanie klucza co 15 minut przy samym przeglądaniu zablokowanej kategorii przeczyłoby sensowi odblokowania.
- Grant jest podpisany razem z `userId` wystawiającego konta i wersją klucza (`accessKeyVersion`, bumpowana przy każdej zmianie/resecie klucza) — nie jest to ciasteczko sesyjne współdzielone między kontami na tym samym urządzeniu, i reset klucza unieważnia natychmiast każdy wcześniej wystawiony grant, bez listy odwołań.
- Linki do pobrania pliku/miniatury/wersji/publicznego widoku są podpisywane (`SignedUrlServiceInterface`, HMAC + timestamp wygaśnięcia) i ważne krótko (900s, publiczne linki dłużej) — bezpośredni dostęp do MinIO nie jest publiczny, zawsze przez podpisany URL wystawiony przez backend. Sygnatura jest liczona po zasobie **wraz z id** (`item-download:{id}`), więc ważnego podpisu z linku jednego itemu nie da się użyć do pobrania innego przez samą podmianę id w URL-u.
- Ten kanał jest celowo niezależny od zwykłej sesji/JWT — endpoint pobierania nie sprawdza roli ani PouchFilter (patrz wyżej); sama zdolność do wystawienia poprawnego podpisu jest dowodem dostępu, bo wystawienie linku (osobny endpoint, z sesją) już przeszło przez normalną autoryzację.
- Ochrona przed CSRF: cookie sesyjne mają `SameSite=Lax`; `OriginCheckListener` dodatkowo sprawdza nagłówek `Origin` (fallback `Referer`) na każdym POST/PUT/PATCH/DELETE przeciwko liście dozwolonych originów.

## Tagi

- `Tag` jest scope'owany per Pouch (`tag.pouch_id`, unikalna nazwa per `(name, pouch_id)`, nie globalnie) — implementuje `PouchAware`, więc `PouchFilter` obejmuje go automatycznie jak `Category`/`Item`.
- `GET /api/tags` zwraca tylko nazwy tagów faktycznie użytych na jakimś aktywnym (nietrasz-owanym) itemie — do filtra/autouzupełniania. `GET /api/tags/all` zwraca każdy tag w pouchu, użyty czy nie — do strony zarządzania.
- Usunięcie tagu nie wymaga, żeby był nieużywany — `item_tag.tag_id` jest `ON DELETE CASCADE`, więc kasuje tylko przypisanie, nic w `Item` nie jest osierocone.

## Wyszukiwarka

- `item.search_vector` — generowana (Postgres `GENERATED ALWAYS AS`) kolumna `tsvector`, ważona per pole (nazwa > tytuł OpenGraph > treść notatki > OCR/opis/URL) — jedno zapytanie trafia przez wszystkie kanały naraz. Dopasowanie po tagu idzie osobno (`ILIKE` na `item_tag`/`tag`), bo generowana kolumna nie sięga do złączonej tabeli.
- Zapytanie użytkownika trafia jako prefix-match (`word:*` per słowo, ANDowane) — pozwala na wyszukiwanie-w-trakcie-pisania bez wymagania całego słowa.
- Gdy dokładne dopasowanie zwróci pustą listę, dopiero wtedy fallback na `pg_trgm`/`similarity()` (tolerancja literówek) — najczęstsza, poprawnie napisana ścieżka nie płaci za to nic dodatkowego.
- To jedyne miejsce, gdzie logika scope'owania per pouch musi być jawna, nie automatyczna — samo dopasowanie idzie przez surowe SQL (`tsvector`/`pg_trgm` nie mają odpowiednika w DQL), poza zasięgiem `PouchFilter`. `?int $pouchId` jest przekazywany jawnie z warstwy serwisu do obu zapytań (dokładnego i fuzzy) — bez tego dokładne trafienie w innym pouchu wpływałoby na to, czy fallback fuzzy w ogóle się uruchamia dla bieżącego pouch.
- Podświetlenie dopasowanego fragmentu (`ts_headline`) liczone jest tylko dla itemów, które faktycznie mają dopasowanie w `search_vector` (nie dla trafień tylko-po-tagu ani fuzzy, gdzie `ts_headline` nie miałby czego podświetlić) — wynik owinięty w sentinel (Private Use Area, nie HTML), bo `ts_headline` zwraca surowy tekst usera (notatka/OCR) i renderowanie go jako markup byłoby wektorem stored-XSS w pouchu współdzielonym między kontami.

## Audit log

- `AuditLogger::log()` zapisuje akcję (podejrzenie/pobranie/usunięcie/zmianę klucza) wraz z pouchem **zasobu**, nie zawsze wywołującego — admin działający cross-pouch loguje pouch celu, żeby dziennik dało się filtrować per pouch w panelu.
- Append-only — nie ma dziś retencji/rotacji (patrz `ROADMAP.md`).

## SSRF (scraping URL-i)

- Każdy fetch URL-a pochodzącego od usera (input przy tworzeniu itemu, `og:image`, `Location` przy przekierowaniu) idzie przez `UrlValidator`/`SafeUrlFetcher` (`src/Services/Item/Scraper/`, `src/Services/Item/Validator/`) — rozwiązuje DNS (A+AAAA), odrzuca hosta, jeśli którykolwiek adres nie jest publicznie routowalny (loopback/RFC1918/link-local, w tym `169.254.169.254`), i **przypina** faktyczny request do zweryfikowanego IP (opcja `resolve` klienta HTTP), żeby druga, niezależna rezolucja DNS przy connect nie mogła odpowiedzieć czymś innym (DNS rebinding).
- `SafeUrlFetcher` sam podąża za przekierowaniami (`max_redirects: 0` + ręczne czytanie `Location`) i waliduje **każdy** hop tą samą ścieżką — inaczej strona kontrolowana przez atakującego mogłaby przekierować dopiero na drugim/trzecim skoku.

## Limity storage

- Maksymalny rozmiar pliku/zdjęcia ma domyślną wartość wbudowaną w kod (`StorageLimitService::DEFAULT_MAX_SIZE_BYTES` — 100 MB dla plików, 25 MB dla zdjęć) i może być nadpisany per-typ w bazie (`StorageLimit` entity) przez panel admina — nadpisanie w bazie zawsze wygrywa z wartością domyślną, domyślna jest tylko fallbackiem dla świeżej instalacji bez skonfigurowanych limitów.

## Storage (MinIO / S3-compatible)

- Pliki nie trafiają na dysk kontenera `app` — idą przez `StorageServiceInterface` (`src/Services/Storage/`) do MinIO (S3-compatible), konfiguracja w `STORAGE_*` (`backend/.env.local`). Lokalnie panel MinIO: `http://localhost:9001`.
- Operacje na tymczasowych plikach lokalnych (np. do OCR/miniaturki w handlerach Messengera) zawsze sprzątają po sobie (`try/finally` + `unlink()`) — plik lokalny to tylko robocza kopia treści, źródłem prawdy jest zawsze obiekt w MinIO.
