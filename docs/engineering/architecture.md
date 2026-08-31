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

## Autoryzacja: konta + access key

- Konta (`User`) są zakładane wprost w bazie, bez samodzielnej rejestracji (patrz `docs/PRODUCT.md`) — logowanie idzie przez JWT.
- Niezależnie od tego istnieje **access key** — mechanizm blokowania pojedynczej kategorii/itemu kluczem (hasłem), sprawdzany przez `AccessKeyGuard`/`AccessKeyService` (`src/Security/AccessKey/`). Poprawne podanie klucza wystawia grant ważny 24h (`AccessKeyService::GRANT_TTL_SECONDS`) — świadomie dużo dłużej niż podpisane linki do pobrania (900s, patrz niżej), bo wpisywanie klucza co 15 minut przy samym przeglądaniu zablokowanej kategorii przeczyłoby sensowi odblokowania.
- Grant jest przypięty do konkretnego zalogowanego użytkownika w momencie wydania — nie jest to np. ciasteczko sesyjne współdzielone między kontami na tym samym urządzeniu.
- Linki do pobrania pliku są podpisywane (`SignedUrlServiceInterface`) i ważne krótko (900s) — bezpośredni dostęp do MinIO nie jest publiczny, zawsze przez podpisany URL wystawiony przez backend.

## Limity storage

- Maksymalny rozmiar pliku/zdjęcia ma domyślną wartość wbudowaną w kod (`StorageLimitService::DEFAULT_MAX_SIZE_BYTES` — 100 MB dla plików, 25 MB dla zdjęć) i może być nadpisany per-typ w bazie (`StorageLimit` entity) przez panel admina — nadpisanie w bazie zawsze wygrywa z wartością domyślną, domyślna jest tylko fallbackiem dla świeżej instalacji bez skonfigurowanych limitów.

## Storage (MinIO / S3-compatible)

- Pliki nie trafiają na dysk kontenera `app` — idą przez `StorageServiceInterface` (`src/Services/Storage/`) do MinIO (S3-compatible), konfiguracja w `STORAGE_*` (`backend/.env.local`). Lokalnie panel MinIO: `http://localhost:9001`.
- Operacje na tymczasowych plikach lokalnych (np. do OCR/miniaturki w handlerach Messengera) zawsze sprzątają po sobie (`try/finally` + `unlink()`) — plik lokalny to tylko robocza kopia treści, źródłem prawdy jest zawsze obiekt w MinIO.
