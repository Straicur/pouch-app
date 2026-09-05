# Pouch — koncepcja produktu

## W skrócie

Prywatne, uporządkowane miejsce na "zaawansowaną dokumentację" — pliki, linki, zdjęcia
i notatki — zastępujące trzymanie tego wszystkiego na Discordzie. Główny cel: mieć jedno
miejsce, z którego można to samo **wyszukać, wysłać i pobrać z dowolnego urządzenia** —
zamiast szukać po historii czatu na telefonie, bo na laptopie akurat nie ma pod ręką.
W przeciwieństwie do Discorda: dane są prywatne, nie znikają bez ostrzeżenia i mają
realną strukturę (kategorie, tagi, wyszukiwarka) zamiast robić porządek z chaosu —
nie chronologiczny strumień wiadomości, w którym trzeba pamiętać, kiedy się coś
wrzuciło, żeby to znowu znaleźć.

Bez rejestracji — konta zakłada admin przez panel administracyjny (`POST
/api/admin/users`), aplikacja obsługuje dziś samo logowanie. Docelowo użytkownik
ustawia i resetuje hasło przez e-mail, z logowania lub ustawień; admin nie resetuje hasła. Zmiana hasła
unieważnia dotychczasowe sesje/tokeny — prace zaplanowane w P0 [roadmapy](ROADMAP.md).
Kont będzie kilka, z różnymi uprawnieniami (patrz "Role i uprawnienia").

## Model pojęciowy

- **Kategorie i podkategorie** — świadomie dokładnie dwa poziomy: kategoria
  główna i jedna warstwa podkategorii, bez dalszego zagnieżdżania. Item zawsze należy
  do dokładnie jednej kategorii. To decyzja upraszczająca produkt, nie brak funkcji.
  **Jeden model, nie dwa** — świadomie zrezygnowaliśmy z
  osobnego bytu "folder" (zbiorcze trzymanie plików bez pełnej ceremonii kategorii):
  podkategoria robi dokładnie to samo, bez dokładania drugiego równoległego drzewa,
  które trzeba by osobno przenosić/przeszukiwać/zabezpieczać. Klucz dostępu na
  podkategorii jest opcjonalny i **dziedziczony z rodzica**, jeśli nie ustawiono
  własnego — "zwykły folder" to po prostu kategoria bez własnego klucza.
- **Item** — rzecz dodana do kategorii, w jednym z typów:
  - **Plik ogólny** (np. `.zip`)
  - **URL** (link do strony)
  - **Zdjęcie** (zdjęcie/zrzut ekranu)
  - **Notatka** (tekst/markdown, pisana bezpośrednio w aplikacji) — obejmuje też
    szybkie wklejenie samego tekstu bez formatowania (np. hasło, które ktoś właśnie
    podał, fragment kodu) — to nie osobny typ, tylko notatka bez markdownowego
    formatowania, wpisywana od ręki, gdy nie ma czasu/potrzeby na coś bardziej
    złożonego.

  Typów ma z założenia przybyć, więc model danych po stronie backendu nie powinien na
  sztywno zakładać, że są tylko te cztery.
- **Tag** — niezależny od drzewa kategorii, przecina je w poprzek; do filtrowania w
  wyszukiwarce.
- **Cykl życia** — każdy item ma TTL albo flagę "trzymaj na zawsze" (domyślnie
  włączoną — nowy item nie znika przez przypadek, dopóki ktoś świadomie nie ustawi
  wygaśnięcia). Działa identycznie niezależnie od tego, gdzie item się pojawia w UI
  (tagi, ulubione) — to właściwość samego itemu, nie widoku.
- **Klucz dostępu** — może chronić zarówno całą kategorię, jak i pojedynczy item z
  osobna (np. wrażliwy plik w ogólnodostępnej kategorii).

## Role i uprawnienia

Jeden fizyczny użytkownik = jedno konto, ale konto może mieć **więcej niż jedną rolę
naraz** (dokładnie tak, jak `User.roles` już działa dziś w backendzie — tablica, nie
pojedyncza wartość).

- **Admin** — wszystko co User, plus panel administracyjny (patrz niżej).
- **User** — pełny dostęp do zarządzania własną (i wg uprawnień: współdzieloną)
  dokumentacją: dodawanie/edycja/usuwanie itemów i kategorii, tagi, ulubione,
  wyszukiwarka.
- **Guest** — wyłącznie podgląd: przeglądanie drzewa, otwieranie/podgląd itemów (o ile
  nie są chronione kluczem, którego nie ma), wyszukiwarka. Bez dodawania, edycji,
  usuwania, bez zarządzania ulubionymi. Rola na później — pod kątem "ktoś jeszcze
  dołączy do projektu i chce zerknąć, bez możliwości czegokolwiek popsuć".

Trzy role (zamiast dwóch) celowo — dają wyraźną, testowalną macierz uprawnień: fixtures
od razu zawierają po jednym koncie na każdą rolę, a testy sprawdzają nie tylko "user
widzi X", ale i "guest NIE może zrobić Y", "user bez klucza NIE widzi Z".

---

## Funkcjonalności użytkownika (User)

### Organizacja i nawigacja
- Kategorie i podkategorie — przeglądanie, dodawanie, **zmiana nazwy i przenoszenie**
  z zachowaniem limitu dwóch poziomów.
- Dodawanie itemów w 4 typach (plik / URL / zdjęcie / notatka) do wybranej kategorii,
  **przenoszenie itemu do innej kategorii, edycja notatki po fakcie**.
- Walidacja przy dodawaniu: jawna lista dozwolonych rozszerzeń/MIME per typ itemu i
  twardy limit rozmiaru (niezależnie od globalnych limitów, które ustawia admin —
  patrz "Funkcjonalności admina").
- **Wykrywanie duplikatów** — hash zawartości pliku liczony przy uploadzie; jeśli
  identyczna zawartość już istnieje, appka o tym informuje zamiast po cichu tworzyć
  drugą kopię (dokumentacja łatwo się dubluje, gdy wrzuca się coś "na szybko").
- Tagi — nadawanie itemom, filtrowanie po nich niezależnie od kategorii.
- Lista ulubionych oraz **widok "ostatnio dodane"** — szybki wgląd w to, co pojawiło
  się w drzewie ostatnio, bez przeszukiwania kategorii.
- Wyszukiwarka — po nazwie, tagach, treści notatek, tekście z OCR (zdjęcia) i
  metadanych URL-i. Na start: full-text search Postgresa (`tsvector`), bez dokładania
  osobnego silnika wyszukiwania — wystarczy do skali jednej/kilku osób.

### Podgląd i dostawa danych
- Podgląd w przeglądarce dla zdjęć, URL-i (metadane OpenGraph) i notatek (renderowany
  markdown).
- Miniatury zdjęć generowane po stronie backendu — lista/galeria kategorii nie ściąga
  oryginałów, żeby się szybko ładowała.
- Pliki serwowane strumieniowo (stream), nie ładowane w całości do pamięci ani po
  stronie backendu, ani frontend nie czeka na cały plik przed startem podglądu/pobrania.
- Dostęp do pliku wyłącznie przez tymczasowy, podpisany URL (ważny np. 15 minut) — nie
  da się linkować pliku na stałe poza aplikacją.

### Cykl życia danych
- Domyślnie "trzymaj na zawsze" — bezpieczniejsze niż domyślne wygasanie, świadomie
  wybrane zamiast pierwotnego założenia TTL = 1 dzień (patrz `CHANGELOG.md`).
- Przy dodawaniu: przełącznik "trzymaj na zawsze" + gotowe presety (1h / 7 dni /
  30 dni) + możliwość wpisania własnej daty wygaśnięcia.
- Kosz — usunięcie nie kasuje danych od razu, item trafia do kosza na 7 dni zanim
  zniknie naprawdę.
- Sprzątanie (wygasłe TTL + docelowe kasowanie z kosza) robi zwykły cron po stronie
  backendu — nie event/trigger przy odczycie, żeby zachowanie było jednakowe niezależnie
  od tego, czy ktoś akurat patrzy na dany item.
- Wersjonowanie — nadpisanie pliku nowszą wersją bez zmiany jego adresu/ID w drzewie
  (czyli linki/odwołania do niego się nie psują).

### Bezpieczeństwo dostępu
- Klucz dostępu — opcjonalny na poziomie kategorii **i** na poziomie pojedynczego itemu.
- Rate limiting na próby wpisania klucza (analogicznie do istniejącego
  `LoginRateLimiter` przy logowaniu) — inaczej klucz można brute-force'ować.
- **Publiczny, czasowy link do pojedynczego itemu** — udostępnienie komuś bez konta w
  appce, ważne np. 24h, generowane ręcznie na żądanie (nie każdy item ma to
  domyślnie). Naturalne rozszerzenie mechanizmu podpisanych URL-i, który i tak
  budujemy pod streaming — tu tylko dłuższy TTL podpisu i świadome kliknięcie
  "udostępnij", zamiast automatycznego 15-minutowego linku do własnego podglądu.
- **Przyszły zakres: odzyskiwanie klucza dostępu przez SMS** — przyjęte do roadmapy;
  szczegóły weryfikacji numeru i przepływu wymagają projektu. Dotyczy kluczy kategorii/
  itemów, a nie hasła konta (reset hasła będzie e-mailowy).

### Metadane
- URL — automatyczne pobranie miniatury/tytułu/opisu (OpenGraph).
- URL — **snapshot treści strony w momencie zapisu** (wyciągnięty tekst, opcjonalnie
  zrzut ekranu), przeszukiwalny tak jak reszta. To ma być dokumentacja — jeśli
  źródłowa strona zniknie albo się zmieni, zapisana treść ma przetrwać. Tania
  rozszerzka scrapera OpenGraph, bo i tak trzeba pobrać stronę.
- Zdjęcie — OCR wyciągający tekst ze zrzutu ekranu, zapisywany pod kątem wyszukiwarki.

### Eksport
- "Pobierz całą kategorię" — strumieniowany ZIP z całą (pod)gałęzią drzewa, z
  zachowaniem struktury folderów.

---

## Funkcjonalności admina

### Zarządzanie treścią
- Dodawanie kategorii.
- Usuwanie cudzych itemów (plik/URL/zdjęcie/notatka) z dowolnej kategorii.
- Reset klucza dostępu — kategorii i pojedynczego itemu.

### Storage i limity
- Podgląd całkowitego zużycia miejsca (dysk/S3), z podziałem na typy plików.
- Globalne limity wagowe per typ (np. max 100 MB na plik zip).

### Automatyzacja czyszczenia
- Dashboard crona odpowiedzialnego za sprzątanie (wygasłe TTL + docelowe kasowanie z
  kosza po 7 dniach).
- Ręczne wywołanie "Run Garbage Collection Now".
- Logi tego, co i kiedy zostało usunięte.

### Audyt
- Dziennik zdarzeń: kto, kiedy, z jakiego IP podejrzał/pobrał/usunął item albo zmienił
  klucz dostępu (kategorii lub itemu).

### Zarządzanie wygasaniem
- Lista itemów wygasających w ciągu najbliższych 24h.
- Masowe przedłużenie ważności wybranych itemów.

### Backup
- Eksport całej struktury kategorii + plików jako jedno archiwum ZIP (lokalny backup).

---

## Wymagania techniczne (backend)

Konsekwencje powyższych decyzji, które trzeba będzie zaprojektować zanim zacznie
powstawać domena:

- **Role i uprawnienia** — 3 role (`ROLE_ADMIN`, `ROLE_USER`, `ROLE_GUEST`) na
  istniejącym polu `User.roles` (tablica, już wspiera wiele ról naraz — nic do zmiany
  w encji). Do tego voter/guard sprawdzający rolę per endpoint + osobno ACL na
  poziomie itemu/kategorii (klucz), czyli dwa niezależne mechanizmy: "czy masz rolę do
  tej operacji" i "czy znasz klucz do tego zasobu".
- **Fixtures per rola** — `AppFixtures` (albo nowa fixture) musi zakładać co najmniej
  jedno konto ADMIN, jedno USER, jedno GUEST, żeby testy mogły od razu budować macierz
  uprawnień, o co explicite prosiłeś.
- **Cron / scheduler** — realny, cykliczny task po stronie backendu (Symfony
  Scheduler albo `bin/console` command spięty z systemowym cronem/Docker) do: (1)
  przenoszenia wygasłych TTL itemów do kosza, (2) trwałego kasowania z kosza po 7
  dniach, (3) generowania danych do admin-dashboardu ("co zostało wyczyszczone").
- **Asynchroniczne przetwarzanie** — OCR, scraping OpenGraph, generowanie miniatur i
  streamowanie ZIP-a kategorii nie mogą blokować cyklu request/response. Kandydat:
  Symfony Messenger z kolejką (na start wystarczy transport na Doctrine, bez
  dokładania Redis/RabbitMQ na dzień dobry).
- **Podpisane URL-e** — mechanizm generowania i walidacji tymczasowego,
  kryptograficznie podpisanego linku do pliku (HMAC + timestamp wygaśnięcia w
  parametrze, weryfikowane przy każdym request do streamu), niezależny od
  auth-tokena użytkownika.
- **Storage** — MinIO/S3 już jest w `docker-compose.yml`; do tego realny streaming
  upload/download (bez buforowania całego pliku w pamięci PHP) i osobna przestrzeń na
  wersje pliku (nadpisanie nie może kasować poprzedniej wersji od razu).
- **Wyszukiwarka** — indeks `tsvector` w Postgresie łączący: nazwę itemu, tagi, treść
  notatki, tekst z OCR, tytuł/opis z OpenGraph. Jedno zapytanie przeszukujące wszystko
  na raz, nie osobne zapytania per typ.
- **Audit log** — osobna tabela (append-only) na zdarzenia mutujące i podglądowe, z
  `userId`, IP, typem akcji, ID zasobu, timestampem. Warto pomyśleć o retencji/limicie
  wielkości tej tabeli już na starcie, skoro loguje też każdy podgląd, nie tylko
  mutacje.

Zdecydowaliśmy się zostawić cały zakres (OCR, wersjonowanie, ZIP kategorii, pełny
audit log, panel admina, snapshoty URL-i, wykrywanie duplikatów, publiczne linki do
pojedynczych itemów) w MVP — żadne z powyższego nie jest już odłożone na później.

## Wymagania techniczne (frontend)

Głównym zastosowaniem appki jest wyszukiwanie/przesyłanie/pobieranie danych **pomiędzy
urządzeniami** — nie tylko z laptopa. To nie jest nice-to-have do doklejenia później,
tylko wymaganie od pierwszego ekranu:

- **Responsywność / mobile-first od startu.** Layout, nawigacja po drzewie kategorii i
  formularze dodawania itemów projektujemy od razu pod telefon, nie tylko pod desktop
  z dorzuconymi media queries na końcu.
- **Upload i podgląd muszą działać wygodnie na telefonie** — w tym dodawanie zdjęcia
  wprost z aparatu (`<input type="file" capture>` albo odpowiednik), nie tylko wybór
  pliku z dysku.
- **Streaming pobierania musi działać tak samo na słabszym łączu mobilnym**, jak na
  Wi-Fi — to konsekwencja tego samego wymagania "z dowolnego urządzenia", nie osobna
  sprawa.
- Do rozważenia przy projektowaniu frontu (nie ustalone jeszcze): czy appka ma być
  zainstalowalna jako PWA, żeby wygodnie dodać ją do ekranu głównego na telefonie.

## Rozważane, ale świadomie poza zakresem

- **Odnośniki między itemami (backlinks, styl Obsidiana)** — item odwołujący się do
  innego itemu, wspólny "graf wiedzy". Ciekawy pomysł koncepcyjnie, ale to jedyna
  rzecz na tej liście, która wymaga zupełnie nowego mechanizmu (parsowanie
  odwołań, przechowywanie grafu, UI do jego przeglądania), zamiast rozszerzać coś,
  co już budujemy. Zostaje poza MVP.
- **Realtime collaborative editing (styl Google Docs)** — złożoność (OT/CRDT,
  presence, rozwiązywanie konfliktów) kompletnie nieproporcjonalna do narzędzia dla
  1–kilku zaufanych osób, które i tak się w praktyce nie edytują nawzajem w tej samej
  sekundzie. Notatka z prostym "ostatni zapis wygrywa" wystarczy.

## Analiza kosztów — czy self-hosting się opłaca

### Ile kosztuje postawienie własnej instancji

| Pozycja | Koszt |
| --- | --- |
| VPS (Hetzner CX22: 2 vCPU / 4 GB RAM / 40 GB) | ~4,35–4,59 €/mies. |
| Storage S3-kompatybilny (Backblaze B2, $0,006/GB/mies., darmowy egress do 3× storage) | grosze przy skali osobistej — np. 100 GB ≈ 0,55 €/mies. |
| Domena | ~1 €/mies. (10–15 €/rok) |
| SSL | 0 € (Let's Encrypt) |
| Backup/snapshot VPS (opcjonalnie) | ~1 €/mies. |
| **Razem** | **~6–8 €/mies. (~75–95 €/rok)**, rośnie łagodnie wraz z realnym zużyciem, nie skokowo jak sztywne pakiety abonamentowe |

### Ile kosztują gotowe rozwiązania (2026)

| Usługa | Co daje | Cena |
| --- | --- | --- |
| [Google One / AI Pro, 2 TB](https://one.google.com/about/plans) | ogólny dysk w chmurze, OCR w wyszukiwarce zdjęć | ~100–220 €/rok (promocje wahają cenę) |
| [Dropbox Plus, 2 TB](https://www.cloudwards.net/dropbox-pricing/) | dysk w chmurze, historia wersji 30 dni | ~110 €/rok |
| [Notion Plus](https://lifestack.ai/blog/notion-pricing) | notatki, bazy danych — **nie** jest to magazyn plików | ~110 €/rok (10 $/user/mies.) |
| [Raindrop.io Pro](https://savethisone.com/blog/raindrop-pricing-2026) | tylko zakładki/URL-e z OpenGraph | ~28–38 $/rok — najtańsze, ale wąski zakres |
| [Nextcloud One (managed)](https://www.nextcloud-one.com/pricing/) | ogólny dysk plików, self-hosted "pod klucz" | ~180–216 €/rok |

### Wniosek

Sama infrastruktura pod Pouch jest **tańsza niż jakikolwiek pojedynczy** z powyższych
abonamentów — i to zanim doliczysz, że żaden z nich w pojedynkę nie robi tego, co ma
robić Pouch. Żeby złożyć to z gotowych klocków (Raindrop na linki + Dropbox/Google na
pliki i zdjęcia + Notion na notatki), wychodzi ~250–270 €/rok w trzech różnych
miejscach — i nadal bez: jednego wspólnego wyszukiwania po wszystkim, automatycznego
TTL+kosza, kluczy dostępu na kategorię/item, własnego audit loga, ani realnej
własności danych (co było Twoim pierwotnym powodem odejścia od Discorda).

Ale uczciwie: **to nie jest projekt o oszczędzaniu pieniędzy** — Discord, od którego
odchodzisz, jest darmowy. ~7 €/mies. to nie próg wejścia wart analizy (mniej niż jeden
Netflix). Prawdziwy koszt tego przedsięwzięcia to nie infrastruktura, tylko **Twój
czas**: napisanie tego (tygodnie pracy) i późniejsze utrzymanie (aktualizacje
bezpieczeństwa, backupy, pilnowanie że działa). Jeśli ten czas liczysz jako projekt
hobbystyczny/naukę — self-hosting wygrywa bez dyskusji, bo infra jest praktycznie
darmowa. Jeśli liczysz swój czas po realnej stawce — żadne z powyższych porównań cen
nie ma znaczenia, bo koszt pracy własnej i tak przewyższy różnicę w abonamentach.
Sensowność tego projektu bierze się z prywatności, kontroli i dopasowania do
Twojego workflow, nie z rachunku €/GB.

## Pomysły na później

Zgłoszone, świadomie nie projektowane jeszcze — nie MVP, ale warto nie zapomnieć:

- **Trzymanie diety** — dziś w osobnym pliku albo na fizycznych kartkach. Pasowałoby
  to prawdopodobnie pod istniejący model (kategoria "Dieta" + notatki/pliki w
  środku), więc raczej nie potrzeba nowego mechanizmu — ale realny kształt (jadłospis
  na dzień/tydzień, listy zakupów, przepisy) do rozpisania osobno, gdy przyjdzie
  kolej na tę funkcjonalność.
