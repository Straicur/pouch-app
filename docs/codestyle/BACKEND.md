# Backend Codestyle (PHP / Symfony)

Ten dokument to nasz codestyle dla backendu — całość, nie tylko to, co
wymuszają narzędzia. Obowiązuje przy każdej zmianie w `backend/`, niezależnie
czy pisze ją człowiek, czy ja (agent). Część "wymuszane automatycznie" nie
wymaga pamiętania — uruchom fixery, a poprawią kod za Ciebie. Część
"konwencje" i dalsze sekcje (DI, testy, migracje, checklisty) trzeba
stosować świadomie przy pisaniu kodu — żaden linter tego nie złapie, więc
złamanie ich to błąd w review, nie tylko styl.

**Zasada nadrzędna:** zmiana w `backend/` nie jest skończona, dopóki
`make cs`, `make rector` i `make phpstan` nie przechodzą czysto (albo
rozjazd jest świadomy i wyjaśniony — patrz sekcja "PHPStan baseline"), a
`make test-backend` jest zielone. Nie proś o review/nie zgłaszaj zadania
jako zrobione bez tego przebiegu.

## Wersje kluczowych komponentów

Generuj kod zgodny z najlepszymi praktykami dla tych wersji (patrz
`backend/composer.json`):

- PHP 8.4
- Symfony 7.4
- Doctrine ORM 3.6
- PHPUnit 12.5
- Dokumentacja API: `nelmio/api-doc-bundle` (OpenAPI/Swagger) — **nie** API
  Platform, projekt go nie używa.

## Jak uruchomić

| Komenda | Co robi |
| --- | --- |
| `make cs` | pokazuje, co php-cs-fixer by poprawił (dry-run) |
| `make cs-fix` | poprawia automatycznie |
| `make rector` | pokazuje, co rector by poprawił (dry-run) |
| `make rector-fix` | poprawia automatycznie |
| `make phpstan` | statyczna analiza, poziom 10 + strict rules |

Uruchamiaj `cs-fix` i `rector-fix` przed każdym commitem — większość reguł
poniżej i tak zostanie wymuszona automatycznie.

---

## Wymuszane automatycznie

### Strict types

Każdy plik PHP musi zaczynać się od (ze spacją wokół `=` — to nie przypadek,
tak ustawia `declare_equal_normalize`):

```php
<?php

declare(strict_types = 1);
```

`declare_strict_types` w cs-fixerze dopisze to automatycznie, jeśli
zapomnisz. Obecne pliki popraw, by z tego korzystały, o ile nie zaburza to
rozlegle projektu.

### Typuj wszystko

PHPStan na poziomie 10 + rector (`typeDeclarations` set) wymagają typów
parametrów, zwracanych wartości i właściwości wszędzie, gdzie da się je
wywnioskować. Bez `mixed` "na wszelki wypadek" — jeśli metoda faktycznie
zwraca `array|bool|float|int|string|null` (tak jak realny błąd, który złapał
nam `phpstan` w `ConfigService::getAccessTokenTimeToLive()`), typ musi to
odzwierciedlać albo kod trzeba zawęzić, a nie zamieść pod dywan.

### Styl Yody dla porównań

`yoda_style` (equal, identical, less_and_greater) — stała/literal po lewej:

```php
// tak (i tak wygląda to już w src/Security/AuthService.php)
if (null === $user) { ... }

// nie
if ($user === null) { ... }
```

### Zawsze `===`/`!==`, nigdy `==`/`!=`

`disallowedLooseComparison` (phpstan strict rules) blokuje luźne porównania.

Niejawne rzutowanie na `bool` (`if ($x)`) jest dopuszczalne **tylko** dla
zmiennych faktycznie otypowanych jako `bool` — to właśnie egzekwują
`booleansInConditions`/`booleansInLoopConditions` w `strictRules` naszego
`phpstan.neon.dist`:

```php
// ok — $isActive: bool
if ($isActive) { ... }

// wymagane dla wszystkiego innego
if (null === $userId) { ... }
if (0 === $count) { ... }
if ('' === $name) { ... }
```

Do sprawdzania "pustości" (np. `null`, `''`, `0`, `[]` naraz) używaj
`empty($value)` — `disallowedEmpty` jest jawnie wyłączone w
`phpstan.neon.dist` (`strictRules.disallowedEmpty: false`) i rector nie
przepisuje `empty()` (`DisallowedEmptyRuleFixerRector` jest w `withSkip`),
więc to legalne, świadome ustawienie tego projektu, nie przeoczenie.

### `&&` / `||`, nie `and` / `or`

`logical_operators` (cs-fixer, risky) zamienia `and`/`or`/`xor` na
odpowiedniki symboliczne.

Jeśli wiesz że sprawdzasz tablicę to porównuj do '[]' zamiast empty bo to jest nadmiarowe

### Krótka składnia tablic i list

`array_syntax: short`, `list_syntax: short`:

```php
$items = [];
[$first, $second] = $pair;
```

### Importuj klasy, nie pisz ich w pełni kwalifikowanych

`global_namespace_import`, `fully_qualified_strict_types`,
`single_import_per_statement`, `no_leading_import_slash`:

```php
use App\Security\AuthServiceInterface;

final class LoginController
{
    public function __construct(private readonly AuthServiceInterface $authService) {}
}
```

nie `\App\Security\AuthServiceInterface` inline w typehincie. Dotyczy to też
klas globalnych (`DateTime`, `Exception`, ...):

```php
use DateTime;
use Exception;

throw new Exception();
$date = new DateTime();
```

Przy konflikcie nazw (dwie klasy `User` z różnych namespace'ów) — alias w
`use`, którego nazwa jasno mówi, skąd ta klasa jest:
`use App\Security\User as SecurityUser;`. Nieużywany `use` zawsze usuwaj —
`no_unused_imports` (część `@Symfony`) i tak go wytnie przy `cs-fix`, więc
równie dobrze zrób to od razu.

**Uwaga:** to dotyczy tylko klas. Wywołania natywnych *funkcji* PHP (nie
klas) mają odwrotną regułę — patrz `native_function_invocation` niżej, tam
`\`-prefiks jest wymagany.

### Natywne funkcje/stałe z `\` w prefiksie

`native_function_invocation` / `native_constant_invocation` (risky, ale
`setRiskyAllowed(true)` jest włączone). W pliku z `namespace App\...` wołanie
natywnej funkcji PHP musi być poprzedzone `\`, żeby resolver PHP nie musiał
szukać jej najpierw w bieżącej przestrzeni nazw:

```php
$count = \count($items);
$upper = \mb_strtoupper($value);
```
Stosuj to tylko przy pojedynczych wywołaniach. Przy kilku rób `use function count;` jako jawny import

### Konstruktor z promocją właściwości + `readonly`

Rector (`ClassPropertyAssignToConstructorPromotionRector`) i cały istniejący
kod promują zależności bezpośrednio w konstruktorze jako `private readonly`:

```php
public function __construct(
    private readonly RequestServiceInterface $requestService,
    private readonly AuthServiceInterface $authService,
) {}
```

**Wyjątek:** encje Doctrine (`src/Entity`) są celowo wyłączone z tej reguły w
`rector.php` (`withSkip`) — Doctrine potrzebuje mutowalnych właściwości bez
promocji w konstruktorze.

### Pusty konstruktor w jednej linii

Customowy fixer `ConstructorEmptyBracesFixer` trzyma `{}` pustego ciała
konstruktora na tej samej linii co lista parametrów (patrz przykład wyżej) —
nie łam tego na osobną linię.

### Przecinek końcowy w wieloliniowych listach

`trailing_comma_in_multiline` (arrays, parametry, `match`,
array-destructuring) — jeśli lista argumentów/elementów łamie się na kilka
linii, ostatni element też dostaje przecinek:

```php
public function __construct(
    private readonly RequestServiceInterface $requestService,
    private readonly AuthServiceInterface $authService,
) {}
```

### Jeśli wywołanie się łamie — łamie się w całości

`method_argument_space` z `on_multiline: ensure_fully_multiline` — nie ma
częściowego zawijania (część argumentów w linii wywołania, część niżej).
Albo wszystko w jednej linii, albo każdy argument na swojej.

### `#[Override]` przy nadpisywaniu metod

`checkMissingOverrideMethodAttribute` (phpstan) wymaga atrybutu `#[Override]`
na każdej metodzie nadpisującej metodę z interfejsu/klasy bazowej:

```php
#[Override]
public function getRoles(): array { ... }
```

### Wczesny return zamiast zagnieżdżonych `if/else`

Rector `earlyReturn` set spłaszcza warunki do guard clauses. Pisz w ten
sposób od razu, zamiast liczyć na to, że rector to później przerobi:

```php
// tak
if (null === $user) {
    throw new UnauthorizedException();
}

return $user;

// nie: zagnieżdżanie reszty logiki w `else`
```

Przy nawigacji po łańcuchu opcjonalnych (`?`) relacji korzystaj z operatora
`?->` zamiast powtarzanych `if ($x === null) { return; }` na każdym kroku —
guard clause zostaje jeden, na końcu:

```php
// tak
$type = $file->getType();
$user = $type?->getUser();

if (null === $type || null === $user) {
    return;
}

// unikaj: if-null-return po każdym kroku łańcucha
```

### Martwy kod nie przetrwa

Rector `deadCode` set usuwa nieużywane prywatne metody/właściwości,
nieosiągalny kod, zbędne `else` po `return`/`throw` (`no_useless_else`) itd.
Nie zostawiaj kodu "na potem" — jeśli nie jest używany, rector go i tak
wytnie przy najbliższym `rector-fix`.

---

## Konwencje projektowe (nie wymuszane automatycznie)

Tooling tego nie sprawdzi — to obserwacja z istniejącego kodu, egzekwowana
w code review.

- **Serwisy programuje się przeciwko interfejsom.** Każdy wstrzykiwany
  serwis (`AuthService`, `TokenService`, `CookieService`, ...) ma swój
  `*Interface`, typehintowany w konsumentach zamiast konkretnej klasy —
  patrz `src/Security/AuthServiceInterface.php` + `AuthService.php`.
- **Klasy są `final` domyślnie**, chyba że są jawnie projektowane do
  dziedziczenia (np. `ApiException`, `ExceptionModel` i ich podklasy w
  `src/ExceptionManagement`, celowo nie-`final`, bo cała hierarchia
  wyjątków na tym polega).
- **DTO**: właściwości `private readonly`, walidacja przez atrybuty
  `Symfony\Component\Validator\Constraints` bezpośrednio na promowanym
  parametrze konstruktora, dostęp wyłącznie przez gettery (żadnych
  publicznych właściwości) — patrz `src/DTO/Request/LoginRequestDTO.php`.
  Parametry z danymi wrażliwymi (hasła, tokeny) oznacz
  `#[SensitiveParameter]`.
- **Rzucaj wyjątki domenowe, nie gołe `\Exception`**, i nie używaj wyjątków
  do sterowania normalnym przepływem — wyjątek zawsze oznacza coś
  wyjątkowego, z jasnym znaczeniem (patrz hierarchia w
  `src/ExceptionManagement`).
- **Każdy wyjątek API ma parę Exception + Model** w tym samym katalogu
  (`src/ExceptionManagement/Exceptions/ApiException/<Nazwa>/<Nazwa>Exception.php`
  + `<Nazwa>ExceptionModel.php`) — Model to serializowalny kształt JSON-a,
  Exception niesie kod HTTP i wiadomość.
- **Każdy endpoint dokumentuje, co rzuca.** Kontroler ma `@throws` w
  PHPDoc nad metodą akcji dla każdego możliwego wyjątku API, i odpowiadający
  mu `#[OA\Response(...)]` na poziomie klasy/metody, żeby Swagger się
  zgadzał z rzeczywistością.
- **Route methods przez stałe**, nie literały:
  `methods: [Request::METHOD_POST]`, nie `methods: ['POST']`.
- **Kody HTTP przez `Response::HTTP_*`**, nie liczby wprost
  (`Response::HTTP_BAD_REQUEST`, nie `400`).
- **Controller jest cienki.** Odpowiada tylko za: request → DTO (przez
  `RequestService`, patrz niżej), wywołanie serwisu/serwisów, zwrócenie
  response. Logika domenowa mieszka w serwisach, nie w akcji kontrolera.
- **`readonly class`** dla obiektów w pełni niemutowalnych (wszystkie
  properties `readonly`) — czytelniejsze niż `readonly` na każdej
  właściwości osobno, gdy dotyczy to całej klasy (typowo: DTO, value
  objecty).
- **Enumy zamiast zamkniętego zbioru stałych.** Tak jak
  `ExceptionUuidEnum` — jeśli dojdzie nowy zamknięty zbiór wartości (statusy,
  typy), rób z niego `enum`, nie zestaw `public const`. Case'y enuma:
  `UPPER_CASE`.
- **`match` zamiast `switch`**, gdziekolwiek to pasuje (brak fallthrough,
  wyrażenie zwraca wartość).
- **Named arguments** tam, gdzie poprawiają czytelność wywołania — już tak
  robimy wszędzie przy tworzeniu DTO/Modeli (`new BadRequestExceptionModel(detail: $message, status: $code)`).
- **Statyczne metody tylko dla czystych, bezstanowych narzędzi bez
  zależności** — jak `UrlResolver::resolve()`. Jeśli metoda potrzebuje
  jakiejkolwiek współpracującej usługi (logger, repozytorium, inny serwis),
  to serwis wstrzykiwany przez DI, nie statyczny helper — tak jak hasła i
  klucze dostępu idą dziś przez `Symfony\Component\PasswordHasher`
  (`UserPasswordHasherInterface`/`PasswordHasherFactoryInterface`), nie
  przez ręczny `password_hash()` w statycznej klasie.
- **Command/Query Separation.** Metoda albo zmienia stan (command), albo
  zwraca dane (query) — nie oba naraz.
- **DTO ≠ Entity.** Encje (`src/Entity`) niosą stan domeny + persistence.
  Wejście/wyjście API to zawsze osobne DTO (`src/DTO/Request`,
  `src/DTO/Response`) — encja nigdy nie wycieka bezpośrednio jako response.
- **Repozytoria wstrzykiwane jawnie przez konstruktor**
  (`private readonly UserRepository $userRepository`), nigdy przez
  `$entityManager->getRepository(User::class)`.
- **Widoczność `const` zawsze jawna**: `private const`, jeśli stała jest
  używana tylko wewnątrz klasy; `public const`, jeśli poza nią też. Nigdy bez
  modyfikatora dostępu.
- **Minimum komentarzy i PHPDoc.** Komentarz tylko tam, gdzie logika jest
  nieoczywista albo niesie kontekst biznesowy, którego nie widać z kodu.
  `@var` dopuszczalny, gdy PHP samo nie potrafi wywnioskować typu. Nie
  duplikuj w PHPDoc tego, co już mówi typ w sygnaturze.

### `RequestService` — dekodowanie i walidacja body requestu

Kontroler nie parsuje `$request->getContent()` ręcznie. Wstrzykuje
`App\Service\RequestServiceInterface` i woła:

```php
$dto = $this->requestService->getRequestBodyContent($request, LoginRequestDTO::class);
```

- DTO żądania: `private readonly` properties przez promocję w konstruktorze,
  gettery, walidacja przez atrybuty `#[Assert\...]` (patrz sekcja DTO wyżej).
- `RequestService` sam rzuca nasze `BadRequestException` przy złym/niedopasowanym
  JSON-ie i `UnprocessableContentException` (z listą `ViolationModel` per
  naruszony constraint) przy błędach walidacji — kontroler ich nie łapie,
  tylko deklaruje w `@throws` (patrz OA\Response wyżej).

---

## Struktura katalogów `src/`

Reguła, nie tylko obserwacja — złamanie tego to błąd w review, tak jak
reszta sekcji "Konwencje projektowe".

- **Nowy moduł domenowy, który to głównie serwisy (interfejs +
  implementacja, ewentualnie kilka pomocniczych klas obok), trafia pod
  `src/Services/<Domena>/`, nie jako osobny folder wprost w `src/` nazwany
  domeną.** Tak jak dziś:
  ```
  src/Services/
      Item/
          ItemServiceInterface.php, ItemService.php
          OcrServiceInterface.php, OcrService.php
          ThumbnailServiceInterface.php, ThumbnailService.php
          StorageLimitServiceInterface.php, StorageLimitService.php
          Validator/
              FileValidator.php, ImageValidator.php, NoteValidator.php, UrlValidator.php
          Collector/
              ItemGarbageCollectorInterface.php, ItemGarbageCollector.php
          Resolver/
              UrlResolver.php
          ValueObject/
              ItemListFilter.php, ItemLifecycleOptions.php
          Scraper/
              OpenGraphScraperInterface.php, OpenGraphScraper.php
              SafeUrlFetcherInterface.php, SafeUrlFetcher.php
              ScrapedPage.php
      Storage/
          StorageServiceInterface.php, StorageService.php
      Tag/
          TagServiceInterface.php, TagService.php
      Request/
          RequestServiceInterface.php, RequestService.php
      Category/
          CategoryServiceInterface.php, CategoryService.php
          CategoryExportServiceInterface.php, CategoryExportService.php
      Audit/
          AuditLoggerInterface.php, AuditLogger.php
  ```
  **Nie twórz** `src/Płatności/` czy `src/Payment/` dla nowej domeny — zawsze
  `src/Services/Payment/`.
  - Historia: do commitu, w którym powstała ta sekcja, `Item`, `Storage`,
    `Tag` (serwisy) i `Service` (`RequestService` — liczba pojedyncza!)
    były cztery osobne, niespójnie nazwane foldery wprost w `src/`. Scalone
    w jedno `Services/` (liczba mnoga) właśnie po to, żeby nie było
    jednocześnie `Service/` i `Services/` obok siebie.
- **W module, który urósł na tyle, że widać w nim wyraźne "rodzaje" klas
  (kilka walidatorów, kilka resolverów...), te rodzaje dostają własny
  podfolder** — `Validator/`, `Collector/`, `Resolver/`, `ValueObject/`, tak
  jak w `Item/` wyżej. Jeden samotny plik danego rodzaju (na razie tylko
  jeden `UrlResolver.php`) i tak dostaje swój `Resolver/` — konsekwencja
  ważniejsza niż "poczekajmy, aż będzie więcej". Nazwa podfolderu w liczbie
  pojedynczej (`Validator/`, nie `Validators/`), zgodnie z resztą `src/`
  (`DTO/`, `Enum/`).
  - **`ValueObject/`, nie `DTO/`, dla wewnętrznych typów modułu**
    (`ItemListFilter`, `ItemLifecycleOptions`) — świadome rozróżnienie, nie
    synonim. DTO w tym projekcie ma jedno znaczenie: kształt requestu/
    response'u na granicy API (`src/DTO/Request`, `src/DTO/Response` —
    patrz "Konwencje projektowe" wyżej), z walidacją `#[Assert\...]` i
    serializacją wprost pod JSON. `ItemListFilter`/`ItemLifecycleOptions`
    nigdy nie dotykają requestu/response'u bezpośrednio — kontroler składa
    je z już zwalidowanego DTO żądania i przekazuje do serwisu jako spójny
    parametr domenowy (Parameter Object). Nazwanie ich "DTO" było mylące —
    stąd `ValueObject/`.
- **Wiadomości Messengera i ich handlery mieszkają razem, w jednym folderze
  `src/Messenger/`** — nie w osobnych `src/Message/` i `src/MessageHandler/`
  (tak to wyglądało wcześniej). Klasa message'a i jej handler to zwykle
  para plików obok siebie w tym samym katalogu:
  ```
  src/Messenger/
      ScrapeUrlMessage.php, ScrapeUrlMessageHandler.php
      ProcessPhotoMessage.php, ProcessPhotoMessageHandler.php
  ```
  Message i jej handler są w tym samym namespace (`App\Messenger`) — handler
  **nie** importuje swojego message'a przez `use`, referencja jest
  bezpośrednia (ten sam namespace).
- Foldery, które **nie** są modułami serwisów i zostają tam, gdzie są:
  `Controller`, `Entity`, `Repository`, `DTO`, `Enum`, `Event`, `Security`,
  `ExceptionManagement`, `Command`, `DataFixtures` — to już są sensowne,
  jednoznaczne nazwy "rodzaju klasy", nie domeny biznesowej, więc reguła
  wyżej ich nie dotyczy.
  - To nie zwalnia ich z porządku w środku — ten sam pomysł co
    `Validator/`/`Collector/`/`Resolver/` w module `Services/` dotyczy też
    tych folderów: jak tylko widać w nich wyraźną grupę klas jednego
    rodzaju, dostają podfolder. `Security/` ma dziś `AccessKey/` (Część 7),
    `Voter/` i `Limiter/` (`AccessKeyRateLimiter(Interface)`,
    `LoginRateLimiter(Interface)`, `RateLimiterGuard(Interface)` —
    wcześniej sześć plików luzem wprost w `Security/`). `ControllerHelper/`
    ma `Traits/` (`AuthorizesRequestsTrait` — trait delegujący do serwisu,
    patrz `AuthorizationServiceInterface`, zamiast powielać
    auth+voter w każdym kontrolerze) i `Factory/`
    (`StreamedFileResponseFactory(Interface)` — try/finally streamowania
    pliku tymczasowego z `@unlink()`, wcześniej wklejane osobno w
    `CategoryController::export()` i `AdminController::backup()`).

---

## Dependency Injection

`config/services.yaml`: `autowire: true` + `autoconfigure: true`, `App\`
rejestruje jako serwisy wszystko w `src/` **poza**: `Entity`, `Kernel.php`,
`Query` (dziś jeszcze nieużywany, zarezerwowany katalog), `DTO`, `Exception`,
`Enum`.

- Kod przeciwko interfejsom (patrz "Konwencje projektowe" wyżej) działa bez
  żadnej ręcznej konfiguracji, **dopóki interfejs ma dokładnie jedną
  implementację** w kontenerze — Symfony wtedy sam aliasuje
  `Interface → Implementacja`. Tak działa dziś każdy nasz `*ServiceInterface`.
- Jeśli kiedyś interfejs dostanie **drugą** implementację (np. dwa sposoby
  przechowywania plików — lokalny dysk i S3/MinIO), autowiring przestanie
  jednoznacznie się domyślać i trzeba będzie dodać jawny alias w
  `services.yaml` (`App\Foo\BarInterface: '@App\Foo\BarS3Implementation'`).
  To pierwsza rzecz do sprawdzenia, jeśli kontener nagle się nie buduje po
  dodaniu drugiej implementacji.
- Klasy w `Enum`, `DTO` nie są serwisami — nie mają zależności, nie
  wstrzykuje się ich, tworzy się przez `new` (DTO) albo są zbiorem
  case'ów (`Enum`).

## Testy

- **Nowa funkcjonalność = testy funkcjonalne (kontroler przez `WebTest`,
  request→response) i integracyjne (współpraca kilku serwisów/repozytorium
  z realną bazą, przez `SystemKernelTestCase`), nie tylko unit testy pojedynczej
  klasy.** Łatwo o tym zapomnieć przy dopisywaniu jednej metody do serwisu — sam
  fakt, że jednostkowo działa, nie znaczy że endpoint faktycznie zwraca to, co
  ma, albo że migracja/repozytorium/serwis dogadują się ze sobą.
- Struktura testu **odzwierciedla strukturę `src/`**:
  `src/Controller/Api/LoginController.php` →
  `tests/Controller/LoginController/LoginControllerTest.php`.
- Testy funkcjonalne kontrolerów dziedziczą po `App\Tests\WebTest` (ma
  gotowy `$this->webClient`, `$this->databaseMockManager`,
  `$this->responseTool` do asercji na wspólny kształt błędów). Testy, które
  potrzebują tylko kontenera/komend, dziedziczą po
  `App\Tests\SystemKernelTestCase`.
- Każdy test opakowany jest w transakcję przez `dama/doctrine-test-bundle` i
  rollbackowany po teście (`SystemKernelTestCase::tearDown`) — dane
  tworzone w teście nigdy nie zostają w bazie, nie trzeba ręcznie sprzątać.
- Nazwy metod testowych: `testScenarioOpisujacyPrzypadek` (np.
  `testLoginCorrect`, `testLoginIncorrectCredentials`) — nazwa mówi *co*
  testujemy, nie *jak*.
- Dane testowe przez dedykowane `*TestDTO` (`tests/DTO/UserTestDTO.php`) +
  `DatabaseMockManager` do zapisu w bazie, nie przez ręczne
  `INSERT`/tworzenie encji w locie w teście.
- `make test-backend` odpala cały pakiet. Pojedynczy plik/klasę:
  `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec app php bin/phpunit tests/Controller/LoginController/LoginControllerTest.php`.

## Migracje i fixtures

- Zmieniasz encję → `make migration` (generuje plik w `migrations/`) →
  **przejrzyj wygenerowany SQL ręcznie** (auto-generowany diff bywa
  nadgorliwy, np. przy zmianie kolejności kolumn) → `make migrate` (odpala
  migrację na dev *i* test DB naraz).
- Dane deweloperskie (nie testowe) przez `src/DataFixtures/AppFixtures.php`
  + `make fixtures`. Fixture implementuje `FixtureGroupInterface`, żeby dało
  się selektywnie ładować grupy (`getGroups(): ['default']`) — nowe zestawy
  danych dokładaj jako nowe klasy z własną grupą, nie rozrastaj jednej
  klasy w nieskończoność.

## PHPStan baseline

`phpstan-baseline.php` grandfatheruje **istniejące** błędy sprzed
wprowadzenia/zaostrzenia reguły — nie jest to sposób na wyciszenie błędu w
nowym kodzie. Jeśli `make phpstan` zgłasza błąd w kodzie, który właśnie
piszesz — napraw go, nie dopisuj do baseline. `--generate-baseline` uruchamia
się świadomie i punktowo (typowo przy podnoszeniu poziomu/reguł), nie jako
skrót do "zielonego CI".

## Pułapki tego projektu

- **Pusta wartość w `backend/.env`/`.env.local` dla zmiennej, która ma być
  sekretem z vaulta (`JWT_PASSPHRASE`, `JWT_PRIVATE_TOKEN`,
  `JWT_PUBLIC_TOKEN`, `POSTGRES_PASSWORD`), przesłania ten sekret cichym
  pustym stringiem** — Symfony nie sięga do `config/secrets/dev/`, jeśli
  zmienna jest gdziekolwiek w `.env*` explicite ustawiona, nawet na `""`.
  Efekt: aplikacja 500-uje na każdym requeście z niejasnym
  `JsonException: Syntax error` w logu — dokładnie taki realny bug
  naprawiliśmy w `backend/.env` (patrz git history). Jeśli dopisujesz nową
  zmienną, która ma iść z vaulta — nie dawaj jej linii w `.env` w ogóle,
  nawet pustej "dla dokumentacji".
- `POSTGRES_PASSWORD`/`DATABASE_URL` w `backend/.env.local` **muszą**
  pasować do tego, co dostaje kontener `db` w root `docker-compose.yml`
  (`app`/`app` domyślnie) — to jeden z niewielu przypadków, gdzie lokalny
  override musi znać coś o infrastrukturze Dockera, nie tylko o Symfony.
- Środowisko `test` **nie ma własnego vaulta** (`config/secrets/` ma tylko
  `dev/`) — `JWT_PASSPHRASE`/`JWT_PRIVATE_TOKEN`/`JWT_PUBLIC_TOKEN` dla testów
  idą wprost z `backend/.env.test.local` (gitignored), generowane przez
  `make test-env-jwt` (`backend/bin/generate-test-jwt.php`), nie z vaulta.
- `config/reference.php` bywa regenerowany przez komendy Symfony
  (`cache:clear`, `debug:container`) bez `declare(strict_types = 1)` — jeśli
  `make cs` znów go złapie, to nie regresja, tylko Symfony nadpisujące własny
  plik. `make cs-fix` i jedziesz dalej.
