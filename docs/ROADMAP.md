# Pouch — plan pracy

Wyłącznie przyszła/bieżąca praca. Zakończone etapy są w
[`CHANGELOG.md`](CHANGELOG.md), model produktu w [`PRODUCT.md`](PRODUCT.md),
a aktualna architektura w [`engineering/architecture.md`](engineering/architecture.md).

Aktualizacja 2026-09-05: konsolidacja przeglądu i decyzji właściciela produktu.
Największe braki dotyczą obsługi kont, codziennego UX i widoczności operacyjnej.
P0 blokuje wpuszczenie realnych użytkowników/danych; P1–P3 określają dalsze priorytety.
Opisy problemów są punktem wyjścia do prac, nie wynikiem nowego pełnego audytu produkcji.
Przed implementacją trzeba sprawdzić aktualny kod i konfigurację wdrożenia.

Punkt kończy się po implementacji, odpowiednich testach, wymaganych kontrolach jakości
z [zasad projektu](engineering/project-rules.md) i, jeśli dotyczy, sprawdzeniu ręcznym.
Testy nowych funkcji powstają razem z funkcją, niezależnie od priorytetu uzupełniania
starszych braków testowych.

## P0 — konta i gotowość produkcyjna

### Hasło ustawiane i odzyskiwane przez użytkownika

Obecny przepływ tworzenia konta wydaje hasło tymczasowe, a panel admina umożliwia jego
reset. Brakuje samodzielnego ustawienia własnego hasła i unieważnienia sesji po resecie.
Docelowo reset hasła wymaga e-maila i odbywa się przez e-mail; admin nie resetuje hasła.

- [ ] Dodać „Nie pamiętam hasła” na logowaniu oraz zmianę/reset własnego hasła
  w ustawieniach po zalogowaniu, przez przepływ e-mailowy.
- [ ] Zapewnić wysyłkę wiadomości oraz jednorazowe, wygasające linki pozwalające
  użytkownikowi ustawić nowe hasło; obsłużyć link zużyty, wygasły i błąd dostarczenia.
- [ ] Usunąć reset hasła z panelu admina i administracyjnego API. Admin nadal tworzy
  konta, ale użytkownik sam ustawia hasło. Zaprojektować pierwsze ustawienie hasła
  przez e-mail; dotychczasowe hasła tymczasowe nie mogą pozostać stałym dostępem
  bez wymuszenia ustawienia własnego hasła.
- [ ] Po skutecznej zmianie/resecie unieważnić dotychczasowe sesje oraz access/refresh
  tokeny danego użytkownika, także na innych urządzeniach, i wymagać ponownego logowania.
- [ ] Pokryć przepływ testami backendu i frontendu, w tym limitami żądań resetu,
  odpowiedzią nieujawniającą istnienia konta i odrzuceniem starych tokenów.

### Twarde blokery wdrożenia

- [ ] **Sekrety produkcyjne Symfony:** domknąć inicjalizację `config/secrets/prod/`
  i dostarczenie `JWT_PASSPHRASE`/`JWT_PRIVATE_TOKEN`/`JWT_PUBLIC_TOKEN`.
  Opisać i zweryfikować setup (`secrets:generate-keys --env=prod`, ustawienie sekretów)
  oraz start czystej instalacji bez `EnvNotFoundException`/500. Sama generacja kluczy
  vaultu nie zastępuje ustawienia wartości sekretów.
- [ ] **Cookie i HTTPS:** zastąpić sztywne `secure: true` w `CookieService`
  konfiguracją odpowiednią dla środowiska; produkcja wymaga secure cookies i HTTPS.
  Sprawdzić reverse-proxy oraz poprawne rozpoznanie HTTPS, bez polegania na localhost.
- [ ] **Backup poza hostem:** wynosić backup PostgreSQL i MinIO do zewnętrznego,
  S3-kompatybilnego storage'u przed pierwszym realnym użyciem. Lokalny wolumen
  `backup-data` nie wystarcza. Ustalić retencję i sygnalizowanie błędów kopii zewnętrznej.
- [ ] **Odtwarzanie:** rozszerzyć istniejący test restore o rzeczywistą weryfikację
  plików MinIO, a nie tylko liczników tabel; sprawdzić odtworzenie kopii off-site
  i zapisać procedurę oraz wynik ostatniego testu.
- [ ] **Health checks:** DB, MinIO oraz Messenger — sprawność transportu i działanie
  workera; awaria zależności musi być widoczna operacyjnie.
- [ ] **Kolejka:** monitoring failed messages/dead-letter queue, powiadomienie o awarii
  oraz procedura diagnozy i ponawiania wiadomości.
- [ ] **Audit log:** konfigurowalna retencja i cykliczne usuwanie przeterminowanych wpisów.
- [ ] **Rate limiting poza logowaniem i access key:** przeanalizować podział między
  nginx a Symfony. Uwzględnić limity ogólnego ruchu/IP na proxy oraz operacji/konta
  w aplikacji, szczególnie reset e-mailowy, upload, eksport i publiczne linki.
  Wynikiem ma być decyzja, konfiguracja i testy limitów, nie samo porównanie narzędzi.
- [ ] **Rotacja JWT i sekretów:** podpiąć istniejące `jwt-rotate-prod` do cyklicznego
  harmonogramu (plan: co miesiąc), monitorować wykonanie i opisać procedurę rotacji
  sekretów. Uwzględnić, że obecna wymiana keypairu wylogowuje wszystkie sesje.
- [ ] **CSP i HSTS:** ustalić produkcyjne originy i rzeczywistą ścieżkę dostarczania
  plików, skonfigurować CSP oraz HSTS przy działającym HTTPS na reverse-proxy;
  zweryfikować podgląd, pobieranie i udostępnianie plików.

## P1 — UX kont i uprawnień

- [ ] Rozszerzyć `whoami`/`WhoAmIResponse` o role lub efektywne uprawnienia;
  obecne `email` i `isAdmin` nie odróżniają gościa od użytkownika.
- [ ] Dodać spójny tryb tylko do odczytu dla `ROLE_GUEST`: ukryć niedostępne dodawanie,
  edycję, przenoszenie/usuwanie, ulubione i zarządzanie kategoriami/tagami/kluczami.
  Zachować dozwolone przeglądanie, wyszukiwanie i odblokowywanie zasobów znanym kluczem.
- [ ] Sprawdzić nawigację, bezpośrednie wejścia na trasy oraz konta z wieloma rolami;
  oprzeć UI na efektywnych uprawnieniach. Votery backendu pozostają źródłem autoryzacji.
- [ ] Dodać testy macierzy guest/user/admin: dostępne akcje działają, niedozwolone
  nie są oferowane; błędy uprawnień nadal mają czytelną obsługę.

## P2 — codzienne użytkowanie

### Edycja itemu i ponawianie przetwarzania

- [ ] Edycja nazwy itemu i opisu pliku.
- [ ] Edycja adresu URL z ponownym pobraniem metadanych/snapshotu.
- [ ] Zmiana TTL oraz „trzymaj na zawsze” przez właściciela itemu, bez panelu admina.
- [ ] Ponowienie OCR/scrapingu po statusie `failed`, z widocznym stanem operacji
  i zabezpieczeniem przed wielokrotnym zleceniem tego samego przetwarzania.

### Upload strumieniowy do backendu

- [ ] Wiele plików naraz, drag & drop oraz kolejka z wynikiem dla każdego pliku.
- [ ] Postęp, anulowanie i ponowienie uploadu; kolejka działająca podczas nawigacji
  w aplikacji oraz wznowienie po zerwaniu połączenia.
- [ ] Zaprojektować przesyłanie strumieniowe do backendu i dalej do storage'u,
  bez ładowania całego pliku do pamięci PHP. Sprawdzić buforowanie proxy i limity.
  Sam streaming nie zapewnia wznowienia — potrzebny jest osobny mechanizm sesji/części
  uploadu, z kontrolą kompletności i sprzątaniem niedokończonych transferów.
- [ ] Zweryfikować pliki do 100 MB na słabym łączu mobilnym: postęp, zerwanie,
  wznowienie, anulowanie oraz limity miejsca i duplikaty przy wielu plikach.

### Telefon, PWA i „Udostępnij → Pouch”

- [ ] Przeprowadzić audyt mobilny: małe ekrany, menu, modale, formularze, kategorie,
  klawiatura ekranowa, dotyk, upload z aparatu i podgląd/pobieranie.
- [ ] Dodać instalowalną PWA i możliwie krótką ścieżkę zapisu tekstu, URL-a lub pliku
  z telefonu, z ograniczeniem liczby kroków i wygodnym wyborem kategorii.
- [ ] Zbadać i wdrożyć share target na Androidzie/iOS w zakresie obsługiwanym przez
  docelowe platformy; przetestować „Udostępnij → Pouch” na urządzeniach i zapewnić
  ścieżkę zastępczą tam, gdzie integracja nie działa. Nie zakładać zgodności platform.
- [ ] W dalszym etapie: rozszerzenie przeglądarkowe „Zapisz do Pouch”.

### Zarządzalne publiczne linki

- [ ] Wybór czasu ważności w szerszym zakresie godzin zamiast sztywnych 24h;
  ustalić presety i granice, pokazywać dokładną datę wygaśnięcia.
- [ ] Lista linków użytkownika z itemem, statusem i terminem ważności oraz ręczne
  unieważnianie ze skutkiem dla kolejnych prób dostępu.
- [ ] Licznik użyć i informacja o ostatnim użyciu; zdefiniować, co liczy się jako
  użycie, podgląd i pobranie (w tym żądania zakresowe i ponowienia).
- [ ] Opcjonalne hasło i limit pobrań, egzekwowane także przy równoległych żądaniach.
- [ ] Zaprojektować stan linków potrzebny do unieważniania i limitów — obecny
  bezstanowy podpisany URL nie wystarczy. Zachować rozdzielenie linków publicznych
  i krótkotrwałych URL-i do własnego podglądu/pobrania.

## P3 — jakość, panel admina i przenośność

### Kontrakt API

- [ ] Generować klienta lub co najmniej typy odpowiedzi, enumy i błędy z
  `/api/doc.json` (Nelmio/OpenAPI), zamiast ręcznej synchronizacji
  `ExceptionUuid`/`ApiErrorBody` z `ExceptionUuidEnum`.
- [ ] Dodać kontrolę aktualności wygenerowanego kontraktu w CI.

### Brakujące testy frontendu

Liczby z przeglądu (11 plików / 38 testów / 45 komponentów) są historycznym punktem
odniesienia, nie aktualnym pomiarem pokrycia. Priorytet mają zachowania i ryzyko.
Zasada testowania nowych funkcji obowiązuje już w
[`codestyle/FRONTEND.md`](codestyle/FRONTEND.md#testy-komponentów-vitest--testing-library).

- [ ] Panel admina: `UsersPage`, `UserRow`, `CreateUserForm` — tworzenie, role,
  blokowanie i usuwanie kont; uwzględnić usunięcie resetu hasła zgodnie z P0.
- [ ] `StoragePage`, `GcPage`, `BackupPage`, `AuditLogPage`, `ExpiringPage`,
  `AdminItemBrowser`, `PouchSwitcher` — szczególnie destrukcyjne akcje, potwierdzenia,
  sukces/błąd API i izolacja kontekstu pouch.
- [ ] `ConfirmDialog` — potwierdzenie wykonuje akcję raz, anulowanie jej nie wykonuje;
  przetestować zachowanie podczas trwającej operacji i po błędzie.
- [ ] Formularze kategorii/tagów: `CategoryForm`, `RenameCategoryForm`,
  `MoveCategoryForm`, `TagForm`, `TagRow` — walidacja i sukces/błąd API.
- [ ] Strony: `FavoritesPage`, `SettingsPage`, `CategoriesPage`, `HomePage`.
- [ ] Pozostałe istotne zachowania: `AppSidebar`, `ThemeSwitch`, `ProtectedRoute`,
  `ErrorBoundary`, `AccessKeyPanel`, `TagsInput`, `ShareButton` oraz brakujące warianty
  zdjęcie/URL/notatka w `AddItemModal`; najpierw sprawdzić istniejące testy.

### Widoczność operacyjna w panelu admina

- [ ] Wyszukiwanie, filtry i paginacja audit logu oraz szczegóły zdarzenia czytelniejsze
  niż sam typ i ID zasobu.
- [ ] Status backupów, kopii off-site i wynik/data ostatniego testu restore.
- [ ] Status workera i failed messages, z możliwością przejścia do diagnozy.
- [ ] Dashboard zdrowia systemu oparty na mechanizmach P0, ze wskazaniem awarii
  i nieaktualnych pomiarów.
- [ ] Historia zużycia storage'u, poza bieżącymi wartościami.

### Pełny eksport/import pouch

- [ ] Eksport całego pouch użytkownika, obejmujący pliki i ich wersje, notatki,
  URL-e/snapshoty oraz strukturę kategorii i podkategorii.
- [ ] Wersjonowany manifest: tagi, TTL/„trzymaj na zawsze”, metadane i powiązania
  zasobów; określić obsługę kosza i zasobów chronionych kluczem.
- [ ] Import do świeżej instalacji z walidacją manifestu, kompletności plików
  i limitów miejsca, bez naruszania izolacji pouch.
- [ ] Test eksport → import potwierdzający odtworzenie treści, wersji i metadanych.
  Backup administracyjny chroni serwer; ten przepływ zapewnia przenośność użytkownika.

## Ustalone decyzje i przyszły zakres

**Kategorie:** świadomie dwa poziomy — kategorie i jedna warstwa podkategorii.
Nie planujemy dowolnie głębokiego drzewa. Decyzja jest zapisana w `PRODUCT.md`;
nie jest to brak funkcji wymagający rozszerzenia modelu.

- [ ] **Odzyskiwanie klucza dostępu przez SMS:** przyjęte do przyszłego zakresu.
  Osobno ustalić weryfikację numeru, dostawcę, koszty, limity prób i procedurę
  odzyskania/resetu klucza kategorii/itemu. To nie jest reset hasła konta — ten idzie
  przez e-mail zgodnie z P0.
- [ ] **Zapisane wyszukiwania / inteligentne kolekcje:** zachować jako pomysł na później,
  bez zobowiązania do MVP.
- [ ] **Trzymanie diety:** zachować pomysł z `PRODUCT.md`; doprecyzować osobno,
  czy wystarczą istniejące kategorie, notatki i pliki.

Odnośniki między itemami w stylu Obsidiana (graf i parsowanie odwołań) oraz realtime
collaborative editing pozostają świadomie poza zakresem. Wracać do nich dopiero po
nowej decyzji produktowej.

## Sugerowana kolejność realizacji

1. E-mailowe ustawianie/reset własnego hasła, usunięcie resetu admina i unieważnianie sesji.
2. Blokery operacyjne P0, w tym off-site backup i sprawdzone odtwarzanie.
3. Spójny interfejs tylko do odczytu dla gościa.
4. Edycja metadanych/TTL itemu i ponawianie OCR/scrapingu.
5. Audyt mobilny, PWA i share target.
6. Upload strumieniowy: wiele plików, postęp, anulowanie i wznowienie.
7. Zarządzalne publiczne linki.
8. Pełny eksport/import pouch.
9. Rozbudowa panelu admina i uzupełnienie istniejących luk testów frontendu.

Generowanie kontraktu API można prowadzić jako osobny etap P3. Testy nowych zachowań
należą do każdego punktu, a operacyjny monitoring P0 nie czeka na dashboard z punktu 9.
