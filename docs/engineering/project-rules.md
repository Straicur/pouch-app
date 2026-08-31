# Zasady projektu

To projekt jednoosobowy (hobby/side project), więc reguły niżej są zwięzłe i praktyczne, nie proceduralne. Zastane odstępstwo w kodzie nie jest wzorcem do naśladowania, ale nie rozszerzaj zakresu zadania tylko po to, żeby po drodze posprzątać niezwiązany dług techniczny.

## Język i styl pracy

- Rozmowa po polsku, kod (nazwy, komentarze) i commity po angielsku.
- Mów wprost, kiedy czegoś nie sprawdziłeś/nie jesteś pewien — nie zgaduj i nie podawaj przypuszczenia jako faktu.
- Kwestionuj pomysł, jeśli jest ryzykowny albo niespójny z resztą kodu, zamiast dowozić go bez komentarza.
- Zatrzymaj się i zapytaj przed czymś trudnym do cofnięcia — migracją kasującą dane, operacją na produkcyjnym MinIO/bazie, albo decyzją o kształcie modelu domenowego (np. co należy do `Item`, co do `Category`). Zwykła zmiana w kodzie aplikacji takiego pytania nie wymaga.
- Utknięcie w tej samej pętli prób i błędów to sygnał, żeby się zatrzymać, zebrać co już wiadomo i zapytać, zamiast próbować dalej po omacku.
- Źródłem prawdy jest kod i `docs/` w repo, nie pamięć z wcześniejszych rozmów — jeśli czegoś w nich nie ma, dopytaj.

## Zakres i jakość zmiany

- Zanim zaczniesz pisać kod, przejrzyj istniejącą implementację w danym obszarze — nowy kod ma pasować do konwencji, które już tam są.
- Najpierw dodaj/zaktualizuj zależność (`make composer ...`, `make npm install ...`), potem dopiero pisz kod, który jej używa.
- Limit 1000 linii na plik kodu (wyjątek: SCSS). Plik już ponad limitem nie musi być dzielony przy okazji niezwiązanej zmiany, ale nie dokładaj mu objętości.
- Brak `TODO` jako namiastki niezrobionej roboty — jeśli coś świadomie zostaje poza zakresem, powiedz to wprost zamiast zostawiać ślad w kodzie.
- Bez długich komentarzy narracyjnych ani cytatów z dokumentacji produktowej w kodzie — komentarz ma wyjaśniać nieoczywistą decyzję, nie opowiadać co robi kod.
- W szczególności: żadnych komentarzy zaczynających się od numeru części/etapu pracy ("Część 16 — ...", "Krok 2 —", "Post-review fix: ...") ani opisujących historię zmiany ("dawniej X, teraz Y bo..."). Historia zmiany żyje w gicie i w `docs/`, nie w kodzie. Komentarz w kodzie tłumaczy WHY dla czytelnika, który widzi tylko ten plik teraz — jedno-dwa zdania, bez kontekstu sesji/rozmowy, w której to powstało.

  ```php
  // źle — narracja, numer części, historia
  /**
   * Część 16 — which pouch this category (and, through it, every item in
   * it) belongs to. A category's subtree always shares one pouch —
   * enforced by CategoryService::create()/move() always taking the pouch
   * from the current user, never from request input.
   */
  #[ORM\ManyToOne(targetEntity: Pouch::class)]

  // dobrze — tylko nieoczywisty fakt, zwięźle
  // A category's subtree always shares one pouch — enforced in CategoryService, not here.
  #[ORM\ManyToOne(targetEntity: Pouch::class)]
  ```

- **W encjach (`src/Entity/`) nie dodawaj żadnych komentarzy w ogóle** — ani narracyjnych, ani jednozdaniowych. Pole/relacja ma być czytelna z samej nazwy i typu; jeśli coś wymaga wyjaśnienia, to wyjaśnienie idzie do serwisu, który tę regułę faktycznie egzekwuje, nie do encji, która ją tylko przechowuje.
- Istotną decyzję albo zmianę zachowania opisz w odpowiednim pliku w `docs/` (patrz [indeks](../README.md)), a nie tylko w treści commita.

## Migracje bazy

- Migrację generuj z encji, nie pisz jej ręcznie od zera. Jeśli migracje nie są jeszcze zakomitowane i jest ich kilka i można je połączyć to najlepiej jakby była 1 migracja wtedy

```bash
make console make:migration
```

- Ręczny SQL w migracji jest ok tylko dla rzeczy, których Doctrine nie umie wygenerować poprawnie (np. konkretna funkcja/rozszerzenie Postgresa) — i wtedy opisz to wprost w migracji, nie chowaj pod nią zwykłej, generowalnej zmiany schematu.
- `make migrate` migruje od razu bazę dev i test — używaj tego, nie migruj ręcznie tylko jednej z nich.
- Po zmianie schematu sprawdź [diagnostics.md](diagnostics.md), które narzędzie (PHPStan/PHPUnit) ma sens uruchomić.

## Backend

- Pełny codestyle (konwencje wymuszane automatycznie i te sprawdzane w review): [`docs/codestyle/BACKEND.md`](../codestyle/BACKEND.md) — przeczytaj przed zmianą w `backend/`.
- Nowy serwis/mapper/DTO ma iść w ślad istniejących w `backend/src/Services/` i `backend/src/DTO/` (interfejs + implementacja dla serwisów, osobne DTO żądania/odpowiedzi, osobny mapper) — nie wymyślaj nowego podziału obok.
- Sprawdzanie uprawnień do zasobu ma iść przez istniejące mechanizmy (`AuthorizesRequestsTrait`, guardy w `Security/AccessKey/`) — nie duplikuj tej logiki lokalnie w kontrolerze.
- **Zmiana w `backend/` nie jest skończona, dopóki `make cs`, `make rector` i `make phpstan` nie przechodzą czysto, a `make test-backend` nie jest zielone** — nie zgłaszaj zadania jako zrobione bez tego przebiegu (szczegóły ewentualnego świadomego rozjazdu: `BACKEND.md`, sekcja "PHPStan baseline").

## Frontend

- Styl i struktura frontendu: [`docs/codestyle/FRONTEND.md`](../codestyle/FRONTEND.md) — przeczytaj przed zmianą w `frontend/`.
- Zanim dodasz nowy UI-element, sprawdź `frontend/src/ui/catalyst` — jeśli coś podobnego już tam jest, użyj tego zamiast pisać od nowa.
- **Zmiana w `frontend/` nie jest skończona, dopóki `make lint` nie przechodzi czysto, TypeScript nie zgłasza błędów, a `make test-frontend` nie jest zielone.**

## Code review

- Przed oceną diffu przeczytaj [`codestyle/BACKEND.md`](../codestyle/BACKEND.md)/[`codestyle/FRONTEND.md`](../codestyle/FRONTEND.md) (zależnie co jest zmieniane) i [`architecture.md`](architecture.md) — złamanie konwencji stamtąd (np. logika domenowa w kontrolerze zamiast w serwisie, `console.*` zamiast `logger`, brak `#[Override]`, item traktowany jakby mógł należeć do dwóch kategorii) to realny finding, nie tylko styl.
- Nie oceniaj samego diffu w oderwaniu od kontekstu — sprawdź plik, którego dotyczy zmiana, w całości, oraz miejsca, które go używają (wywołania serwisu, testy, handler Messengera po drugiej stronie kolejki), żeby złapać niespójność, której nie widać w samych zmienionych liniach.
- Reguły z `architecture.md` (TTL/trash/GC, kolejka Messengera, access key vs podpisane linki, limity storage) mają pierwszeństwo przy ocenie, czy zmiana w danym obszarze jest poprawna domenowo, nie tylko czy się kompiluje/przechodzi testy.
- Jeśli reguła projektowa (`project-rules.md`/`codestyle/*.md`) jest przestarzała względem tego, co faktycznie widać w kodzie na branchu docelowym, zgłoś to wprost zamiast cicho zignorować dokument albo cicho zignorować kod.
