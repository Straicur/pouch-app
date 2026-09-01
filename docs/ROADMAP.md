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

- [ ] Automatyczny backup PostgreSQL i MinIO (dziś `BackupPage`/`AdminController` robi to ręcznie na żądanie, nie na harmonogramie).
- [ ] Test odtwarzania backupu (nie tylko jego tworzenia).
- [ ] Health checks dla DB, MinIO i kolejki Messengera.
- [ ] Monitoring failed messages / dead-letter queue (Messenger).
- [ ] Retencja audit logów (dziś rosną bez limitu — patrz `AuditLogger`).
- [ ] Limity liczby requestów też poza logowaniem/access key (dziś rate limiting jest tylko na `LoginRateLimiter`/`AccessKeyRateLimiter`).
- [ ] Procedura rotacji kluczy JWT i sekretów (`JWT_PASSPHRASE`/`JWT_PRIVATE_TOKEN`/`JWT_PUBLIC_TOKEN` — patrz `BACKEND.md`'s "Pułapki tego projektu").

---

## Opcjonalne do naprawy (niezależne od kolejności wyżej)

Nie blokują żadnej części — zrobić przy okazji, kiedy akurat dotykamy powiązanego kodu, albo osobno gdy będzie chwila:

- [ ] **Cookie `secure: true` na sztywno** (`CookieService`) — działa dziś tylko dzięki wyjątkowi przeglądarek dla `http://localhost`. Do ogarnięcia przed pierwszym wdrożeniem pod realną domeną (HTTPS na reverse-proxy musi faktycznie działać end-to-end).
- [ ] **Brak prod stage dla backendu** — `backend/Dockerfile` ma tylko `base`+`dev` (frontend ma `prod` z nginx, backend nie). Potrzebne przed pierwszym realnym wdrożeniem, nie wcześniej.

---

## Produktowo (pomysły na kolejne funkcje, po etapie stabilizacyjnym)

- [ ] Szybkie dodawanie materiałów z telefonu — PWA/share target.
- [ ] Import z przeglądarki / rozszerzenie "zapisz do Pouch".
- [ ] Kosz z możliwością ręcznego przywracania (dziś trash → GC jest jednokierunkowe do momentu `purgeTrash()`, ale nie ma UI do "przywróć z kosza" przed tym).
- [ ] Widok ostatnich/nieprzeczytanych elementów.
- [ ] Zapisane wyszukiwania lub inteligentne kolekcje.
- [ ] Eksport i pełna przenośność danych (rozszerzenie eksportu kategorii na cały pouch).

**Nie blokuje** niczego z etapu stabilizacyjnego — to osobny, późniejszy wątek.
