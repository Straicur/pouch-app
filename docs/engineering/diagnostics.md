# Narzędzia diagnostyczne

Ten plik jest wersjonowanym źródłem prawdy o narzędziach diagnostycznych używanych podczas AI self-review. Katalog opisuje PHPStan i PHPUnit dla backendu oraz Biome i Vitest dla frontendu; będzie rozszerzany wraz z dodawaniem kolejnych narzędzi.

Przed rozpoczęciem diagnostyki agent zapoznaje się z aktualną treścią tego pliku i na podstawie zakresu zmian wybiera wszystkie właściwe narzędzia.

## PHPStan

### Do czego służy

PHPStan wykonuje statyczną analizę kodu backendu PHP (`backend/phpstan.neon.dist`). Wykrywa m.in. niezgodności typów i błędne wywołania metod bez konieczności uruchamiania danej ścieżki aplikacji.

### Kiedy należy go użyć

Gdy zmiana obejmuje kod PHP analizowany przez `backend/phpstan.neon.dist`, albo zmienia zależności/konfigurację/kontrakty mogące wpłynąć na analizowany kod. Nie jest wymagany dla zmian dotyczących wyłącznie dokumentacji albo frontendu.

### Jak go uruchomić

```bash
make phpstan
```

Polecenie czyści `var/cache/dev` w kontenerze `app`, ponownie zrzuca kontener DI (`debug:container`) i dopiero potem odpala PHPStan — nie zastępuj tego samym `cache:clear`: kontener rozgrzany przez `cache:clear` potrafi nie zawierać części parametrów bundle'i (np. TTL tokenu JWT), co psuje analizę typów zależnych od `ParameterBagInterface`.

### Jak rozpoznać wynik

- `PASS` — polecenie zakończyło się kodem `0` i PHPStan nie zgłosił błędów.
- `FAIL` — PHPStan zgłosił co najmniej jeden błąd albo polecenie zakończyło się niezerowym kodem.
- `UNAVAILABLE` — analiza nie mogła zostać wykonana z powodu środowiska (np. niedziałający kontener `app`).

Nie regeneruj `phpstan-baseline.php` ani nie używaj mechanizmu pomijania PHPStan w celu ukrycia nowych błędów.

## PHPUnit

### Do czego służy

PHPUnit uruchamia automatyczne testy backendu (`backend/phpunit.dist.xml`). Potwierdza oczekiwane zachowanie kodu i pomaga wykrywać regresje.

### Kiedy należy go użyć

Gdy zmiana modyfikuje zachowanie backendu, naprawia błąd, dodaje/zmienia testy, albo może wpłynąć na istniejące scenariusze backendowe.

### Jak go uruchomić

```bash
make test-backend
```

Polecenie uruchamia `php bin/phpunit` w kontenerze `app` na bazie testowej (`backend/.env.test.local`). Schemat bazy testowej trzeba mieć zmigrowany — `make migrate` migruje jednym poleceniem zarówno bazę dev, jak i test.

### Jak rozpoznać wynik

- `PASS` — polecenie zakończyło się kodem `0`, bez błędów i niezaliczonych testów.
- `FAIL` — co najmniej jeden błąd, niezaliczony test albo niezerowy kod zakończenia.
- `UNAVAILABLE` — testów nie można było wykonać z powodu środowiska (np. niedziałający kontener `app` lub `db`, niezmigrowana baza testowa).

Ostrzeżenia i pominięte testy odnotuj w raporcie, nawet jeżeli nie powodują niezerowego kodu zakończenia.

## Biome (lint frontendu)

### Do czego służy

Biome sprawdza styl i podstawowe błędy w kodzie TypeScript/React (`frontend/biome.json`).

### Kiedy należy go użyć

Dla każdej zmiany w `frontend/src/`.

### Jak go uruchomić

```bash
make lint
```

`make lint-fix` stosuje automatyczne poprawki tam, gdzie to możliwe.

### Jak rozpoznać wynik

- `PASS` — polecenie zakończyło się kodem `0`.
- `FAIL` — Biome zgłosił co najmniej jeden błąd.

## Vitest (testy frontendu)

### Do czego służy

Testy jednostkowe/komponentowe frontendu (konfiguracja Vite/Vitest, `frontend/src/test-setup.ts`).

### Kiedy należy go użyć

Gdy zmiana modyfikuje zachowanie frontendu, naprawia błąd, dodaje/zmienia testy, albo może wpłynąć na istniejące scenariusze UI.

### Jak go uruchomić

```bash
make test-frontend
```

### Jak rozpoznać wynik

- `PASS` — polecenie zakończyło się kodem `0`, bez niezaliczonych testów.
- `FAIL` — co najmniej jeden niezaliczony test albo niezerowy kod zakończenia.
- `UNAVAILABLE` — testów nie można było wykonać z powodu środowiska (np. niedziałający kontener `frontend`).
