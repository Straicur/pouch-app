# Frontend Codestyle (React / TypeScript)

Cały codestyle dla frontendu, nie tylko to co wymusza tooling. Obowiązuje przy każdej
zmianie w `frontend/`. Zasada nadrzędna, tak jak na backendzie: zmiana nie jest
skończona, dopóki `make check`/`make lint` nie przechodzi czysto, `tsc` nie zgłasza
błędów i `make test-frontend` jest zielone.

## Wersje kluczowych komponentów

Patrz `frontend/package.json` (źródło prawdy — jeśli się rozjedzie z tą listą, wygrywa
`package.json`):

- Node.js 22
- React 18
- TypeScript 5.6
- Vite 5

Bez SASS/SCSS. **Tailwind v4** (`@tailwindcss/vite`, konfiguracja CSS-first w
`src/index.css`) wszedł w Części 11 *scoped* tylko do `src/ui/catalyst/` — od Części 13
to już nieaktualne: **Catalyst UI/Tailwind jest standardem dla całej aplikacji**,
plain CSS nie jest już domyślnym wyborem dla nowego kodu. `src/ui/catalyst/`
(przeniesiony i rozbudowany z `e-rezerwacja-frontend`'owego
`libs/catalyst-ui`/`libs/ui`, patrz tamtejszy `README.md`) trzyma komponenty —
`button`, `dialog`, `dropdown`, `badge`, `text`, `heading`, `form/*`
(`fieldset`/`input`/`textarea`/`select`/`checkbox`/`radio`/`switch`) obok
wcześniejszych `link`/`navbar`/`sidebar*`/`touch-target`. Dwie świadome różnice
względem źródła, konsekwentnie w każdym portowanym pliku: brak wymaganego
`dataTestId` (projekt nie ma konwencji `data-testid`) i stockowa paleta
Tailwinda (`zinc`/`blue`/...) zamiast rebrandowanych tokenów źródła
(`bg-primary`/`gray-01`/`gray-02`/`gray-03` itd.) — `src/ui/catalyst/README.md`
ma pełny opis. Istniejące plain-CSS strony (`src/index.css`) migrują stopniowo,
nie jednym commitem — nowy/przerabiany kod idzie od razu przez Catalyst.

**Tryb jasny/ciemny** (`src/libs/theme.ts`) steruje klasą `.dark` na `<html>`
(`@custom-variant dark (&:where(.dark, .dark *));` w `index.css`, patrz jego
komentarz) zamiast samego `prefers-color-scheme` — zapisany w `localStorage`,
`themeUtil.init()` wołane raz w `main.tsx`. Przełącznik: `ThemeSwitch`
(`src/modules/shared/view/ThemeSwitch.tsx`) w `SidebarFooter` obu layoutów.

## Jak uruchomić

| Komenda | Co robi |
| --- | --- |
| `make lint` | biome lint (dry-run) |
| `make lint-fix` | biome lint --write |
| `npm run check` / `check:fix` | lint + format razem (dry-run / write) |
| `npm test` | vitest run |

---

## Struktura katalogów

Od Części 12: pełny podział na `pages/` (routing) i `modules/` (implementacja),
plus wydzielone `libs/`/`utils/`/`providers/`/`store/types/`. Zasada nadrzędna: jeden
katalog na konkretny, samodzielny kawałek funkcjonalności, nie jeden płaski worek na
wszystko (tak jak kiedyś `pages/`/`components/` mieszały wszystko, np. jeden
`AdminPage.tsx` na pięć niepowiązanych sekcji — patrz historia w `CHANGELOG.md`, Część 11
zaczęła ten podział przez `modules/`, Część 12 dociąga go do końca). Nazwy katalogów: liczba
mnoga, mała litera (`libs/`, `utils/`, `pages/`, `modules/`, `providers/`, `types/` —
nie `lib/`, `Utils/` itd.).

```
src/
  pages/                    # WSZYSTKO co ma routing (routes.tsx) i jest stroną główną
    RootLayout.tsx           # nawigacja poza React Routerem (toast/navigate), bez podziału User/Admin
    HomePage.tsx
    LoginPage.tsx
    user/
      UserLayout.tsx         # nawigacja (Sidebar) + <Outlet/> dla /user/*, owinięte ProtectedRoute
      items/ItemsPage.tsx
      categories/CategoriesPage.tsx
    admin/
      AdminLayout.tsx        # nawigacja + jedno sprawdzenie 403 dla całego /admin/*
      storage/StoragePage.tsx
      gc/GcPage.tsx
      auditLog/AuditLogPage.tsx
      expiring/ExpiringPage.tsx
      backup/BackupPage.tsx
  modules/                  # implementacja pod stronami z pages/ — NIC stąd nie ma routingu
    user/
      shared/                # współdzielone MIĘDZY modułami usera (np. AccessKeyPanel)
      items/
        forms/                # formularze (react-hook-form + zod) — NoteForm, FileUploadForm, ...
        view/                 # mniejsze komponenty widoku (karty, przyciski, filtry) — ItemCard, ShareButton, ...
        services/             # logika API/biznesowa specyficzna dla tego modułu (jeśli powstanie — patrz niżej)
      categories/
        view/
    admin/                  # analogicznie, jeśli/gdy admin doczeka się własnych forms/view (dziś strony są proste)
  providers/                # cross-cutting, owija drzewo aplikacji — ProtectedRoute, ErrorBoundary
  libs/                     # infrastruktura: httpClient, logger, toastUtil, i18n, apiError, apiEndpoints...
  utils/                    # czyste funkcje pomocnicze bez efektów ubocznych infrastruktury — accessGrants, triggerDownload
  store/
    api/                    # RTK Query slice'y (createApi + hooki) — jedna sprawa na plik
    types/                  # typy request/response wyniesione z api/*.ts, gdy jest ich dużo (patrz niżej)
```

- Strona widoczna w routingu (`routes.tsx`) zawsze nazywa się `<Nazwa>Page.tsx` i żyje w
  `pages/` — bez wyjątku dla `admin/` (do Części 12 admin używał `<Nazwa>Module.tsx`;
  mylące, bo sugerowało coś z `modules/`, mimo że to były strony z routingiem tak samo
  jak `user/`). `Module`/inne przyrostki nie oznaczają routingu — jeśli coś w
  `modules/` kiedyś potrzebuje własnej nazwy-etykiety, nie bierze przyrostka `Page`,
  właśnie żeby to rozróżnienie zostało jednoznaczne.
- `pages/user/` i `pages/admin/` to jedyny podział na obszary — powstaje **od razu**,
  nie dokłada się go później. `pages/` samo (bez `user/`/`admin/`) zostaje dla tego, co
  nie należy do żadnego obszaru: `HomePage`, `LoginPage`, `RootLayout` (poza-routerowa
  nawigacja/toasty — patrz "Toasty" niżej — nie jest per-obszar, więc nie dzieli się na
  `user`/`admin`).
- `modules/<obszar>/<feature>/` trzyma to, co dana strona z `pages/` renderuje, ale co
  samo nie jest routowalne: `forms/` dla formularzy react-hook-form+zod, `view/` dla
  mniejszych komponentów prezentacyjnych (karty, przyciski, listy, filtry). Podział jest
  po tym, czy komponent zarządza własnym stanem formularza/wysyłką, nie po rozmiarze
  pliku.
- `services/` (w `modules/<obszar>/<feature>/services/`) to miejsce na logikę
  biznesową/wywołania API specyficzne dla jednego modułu, gdy przestaje się mieścić
  wygodnie w komponencie z `forms/`/`view/` — dziś żaden moduł jeszcze tego nie
  potrzebuje (logika mieści się w RTK Query hookach z `store/api/` wprost), katalog
  zakłada się dopiero, gdy faktycznie powstanie taki plik.
- Komponent użyty w więcej niż jednym module danego obszaru → `modules/<obszar>/shared/`
  (przykład: `AccessKeyPanel` używany przez `items` i `categories`). Komponent
  współdzielony między `user` i `admin` → **`src/modules/shared/`** (rozstrzygnięte
  w Części 13, pierwszy przykład: `modules/shared/view/ThemeSwitch.tsx`, w
  `SidebarFooter` obu layoutów) — `src/providers/` zostaje tylko dla cross-cutting
  owijaczy drzewa (patrz niżej), nie zwykłych komponentów widoku.
- `<Obszar>Layout.tsx` (`UserLayout.tsx`, `AdminLayout.tsx`) odpowiada za nawigację i
  jedno wspólne sprawdzenie dostępu (patrz "Layouty i trasy" niżej) — moduły w środku
  nie duplikują tego sprawdzenia.
- `src/providers/` to komponenty, które owijają (część) drzewa aplikacji i same nie
  renderują żadnego widoku dla użytkownika w normalnym przypadku: `ProtectedRoute`
  (sprawdzenie sesji) i `ErrorBoundary` (patrz "Error handling" niżej). Nie mylić z
  `<Obszar>Layout.tsx` w `pages/` — te renderują realną nawigację (Sidebar), nie tylko
  sprawdzają dostęp.
- `src/libs/` to infrastruktura współdzielona w całej aplikacji, zwykle z zależnością na
  coś zewnętrznego/efektem ubocznym (axios, i18next, `console.*`, `sessionStorage`
  pośrednio przez inne `libs/`): `httpClient.ts`, `httpMethods.ts`, `logger.ts`,
  `toastUtil.ts`, `navigationUtil.ts`, `i18n.ts`, `apiError.ts`, `apiEndpoints.ts`,
  `redirectEndpoints.ts`.
- `src/utils/` to czyste(-sze) funkcje pomocnicze — bez frameworkowych zależności, choć
  mogą wołać coś z `libs/` (np. `logger`): `accessGrants.ts` (wrapper na
  `sessionStorage`), `triggerDownload.ts` (nawigacja przeglądarki). Rozróżnienie
  `libs/` vs `utils/` jest z natury trochę płynne — jeśli wątpliwości, pytanie
  pomocnicze: "czy to infrastruktura, na której buduje się reszta apki (`libs/`), czy
  pojedyncza, samodzielna funkcja pomocnicza (`utils/`)".
- `store/types/<nazwa>.ts` — typy request/response z `store/api/<nazwa>Api.ts`
  wynosi się tutaj, gdy jest ich dużo (orientacyjnie: więcej niż 4–5 eksportowanych
  typów w jednym pliku API zaczyna przesłaniać sam kod endpointów przy scrollowaniu —
  tak było z `itemApi.ts`, 15 typów, i `adminApi.ts`, 7 typów). Ten sam próg/wzorzec
  dotyczy nie tylko `store/api/` — jeśli jakiś moduł (`modules/...`) sam narośnie
  własnymi typami, wydziela się analogiczny `types/` katalog lokalnie, nie tylko w
  `store/`. Plik z API slice'em importuje typy stamtąd (`import type {...} from
  "../types/<nazwa>"`) zamiast je deklarować u siebie; konsumenci spoza `store/api/`
  importują typy z `store/types/`, nie z pliku API (hooki typu `useListItemsQuery`
  zostają importowane z `store/api/`, tylko same typy się przenoszą).

## Wymuszane automatycznie

Z `frontend/biome.json` i `tsconfig.app.json`.

### Strict TypeScript

`tsconfig.app.json` ma `"strict": true` + `noUnusedLocals`, `noUnusedParameters`,
`noFallthroughCasesInSwitch`, `noUncheckedSideEffectImports`. To już m.in. daje:

- `catch (error)` ma typ `unknown`, nie `any` — `useUnknownInCatchVariables` jest
  częścią `strict`, nic nie trzeba pilnować ręcznie.
- nieużywany import/zmienna to błąd kompilacji, nie tylko warning lintera.

### `noExplicitAny`

Biome (`suspicious.noExplicitAny: warn`) ostrzega przy `any`. Jeśli typ faktycznie
nie jest znany (np. `unknown` z `catch`), zawężaj go (`instanceof`, `typeof`, guard),
nie rzutuj na `any`.

### `const` zamiast `let`, jedna deklaracja na instrukcję

`useConst`, `useSingleVarDeclarator`:

```ts
// tak
const a = 1;
const b = 2;

// nie
let a = 1;
const a = 1, b = 2;
```

### Import/export type jawnie oddzielony

`useImportType`, `useExportType` — import samego typu przez `import type`:

```ts
import type { AxiosError } from "axios";
import { httpClient } from "./httpClient";
```

### Nawiasy klamrowe zawsze w blokach warunkowych

`useBlockStatements`:

```ts
// tak
if (!ref.current) {
  return;
}

// nie
if (!ref.current) return;
```

### `<>...</>` zamiast `<React.Fragment>`

`useFragmentSyntax`.

### Samo-zamykające się elementy bez dzieci

`useSelfClosingElements` — `<Foo />`, nie `<Foo></Foo>`.

### `===`/`!==`

`noDoubleEquals`.

### `CONSTANT_CASE` dla wartości enumów

`useNamingConvention` (selector: `enumMember`). Obiekty, które celowo emulują enum
(mapa stałych, nie `enum`) — jak `ApiEndpoints` — są wyłączone z reguły per plik przez
`overrides` w `biome.json`, bo reguła sama nie odróżnia "to jest enum" od "to jest
zwykły obiekt z camelCase kluczami".

### `useExhaustiveDependencies`

Tablica zależności `useEffect`/`useCallback`/`useMemo` musi być kompletna — Biome to
sprawdza (warning). Nie wyciszaj przez pusty `// eslint-disable`-owy odpowiednik bez
realnego powodu wypisanego w komentarzu.

## Testy komponentów (Vitest + Testing Library)

Nowe lub zmieniane zachowanie wymagające weryfikacji dostaje testy w tej samej zmianie.
Dotyczy to zwłaszcza formularzy i walidacji, uprawnień i tras, mutacji API,
operacji destrukcyjnych oraz współdzielonych komponentów sterujących tymi przepływami.
Testuj zachowanie widoczne dla użytkownika: sukces, istotne błędy, a przy usuwaniu
również potwierdzenie i anulowanie. Poprawka błędu zachowania powinna mieć test regresji.
Nie odkładaj testów nowej funkcji do etapu uzupełniania starszych braków z roadmapy.
Zmiany wyłącznie wizualne lub statyczna treść nie wymagają sztucznych testów
odwzorowujących implementację; sprawdź je odpowiednio ręcznie.

Testy żyją w osobnym, top-levelowym `frontend/tests/`, nie obok testowanego pliku w
`src/` — nowy test zawsze idzie pod `tests/`, odzwierciedlając ścieżkę testowanego
pliku w `src/` (np. `src/pages/LoginPage.tsx` → `tests/pages/LoginPage.test.tsx`).

Współdzielone narzędzia w `tests/testUtils/`:

- `renderWithProviders(ui)` — renderuje komponent owinięty świeżym (per-test) Reduxowym
  store'em (te same reducery/middleware co `store/store.ts`, ale nowa instancja, żeby cache
  RTK Query nie przeciekał między testami), `MemoryRouter` i `ToastContainer` (bez niego
  `toastUtil.showToast(...)` nic nie renderuje — patrz `RootLayout.tsx`). Użyj `wrapper`
  Testing Library pod spodem, nie ręcznego zagnieżdżenia w JSX-ie — dzięki temu zwrócony
  `rerender(...)` też przechodzi przez te same providery.
- `mockApiResponse(data)`/`mockApiError(status, body)` (`testUtils/mockHttp.ts`) — budują
  wartości zwracane przez zamockowany `httpMethods`.

Każdy plik testujący komponent, który woła RTK Query, mockuje `src/libs/httpMethods` (nie
`axios` ani `httpClient` bezpośrednio) — to jedyne miejsce, przez które `axiosBaseQuery`
faktycznie robi request (patrz `store/api/baseQuery.ts`):

```ts
vi.mock("../../../../../src/libs/httpMethods", () => ({
  httpMethods: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() },
}));
```

Dzięki temu prawdziwe slice'y RTK Query (tagi, `invalidatesTags`, `onQueryStarted`) działają
tak jak w apce — mockowany jest tylko transport, nie warstwa cache'a. Błąd mockuje się przez
`mockApiError(status, body)` wołane leniwie (`mockImplementation(() => mockApiError(...))`,
nie `mockReturnValue(mockApiError(...))`) — odrzucony `Promise` stworzony zbyt wcześnie
(przed jego faktycznym `await`) Node/Vitest zgłasza jako unhandled rejection, nawet jeśli
docelowo zostaje obsłużony.

`onQueryStarted` w każdym `store/api/*.ts` musi łapać błąd z `queryFulfilled` (`try { await
queryFulfilled; ... } catch { /* obsłużone przez .unwrap() u wołającego */ }`) — bez tego
nieudana mutacja zostawia nieobsłużone odrzucenie promise'a (realny bug znaleziony przy
pisaniu testów dla `accessKeyApi.ts`, patrz `CHANGELOG.md`/git log).

---

## Konwencje projektowe (nie wymuszane automatycznie)

### Komponenty

- Funkcyjne, **named export** (`export function LoginPage() { ... }`), nie
  `export default`. Lepsze wsparcie IDE (auto-import pod właściwą nazwą, bezpieczny
  rename), mniej boilerplate przy re-eksportach.
- Zwracany typ (`JSX.Element`) zostaw wywnioskowany — TS i tak go poprawnie
  wywnioskuje, jawna adnotacja nic tu nie zabezpiecza, tylko dodaje szum.
- Krótkie, zgodne z Single Responsibility — logika biznesowa w hookach/serwisach, nie
  w ciele komponentu.

### Props

- `interface`, nazwa zawsze `<Komponent>Props` (nigdy goły `Props`):

```ts
interface LoginFormProps {
  onSuccess: () => void;
}

export function LoginForm({ onSuccess }: LoginFormProps) {
  ...
}
```

- Destrukturyzacja wprost w sygnaturze funkcji (jak wyżej) — nie przez pośredni
  parametr `props`.
- Żadnych inline'owych typów propsów — zawsze nazwany `interface`.

### Hooki

- Logika biznesowa w custom hookach (`useSomething`), nie w ciele komponentu.
- Nazwa zawsze z prefiksem `use` — to nie tylko konwencja czytelności: reguła
  `useHookAtTopLevel` (i cała reszta reguł hooków) rozpoznaje hooki właśnie po tym
  prefiksie, żeby wiedzieć co sprawdzać.

### Layouty i trasy

- Każdy obszar (`UserLayout`, `AdminLayout` — patrz "Struktura katalogów" wyżej) jest
  owinięty `ProtectedRoute` (`src/providers/ProtectedRoute.tsx`) — jedno sprawdzenie
  sesji (`useWhoAmIQuery`) dla całego poddrzewa tras zamiast każdej strony osobno.
  Dodając nowy obszar (nie nowy moduł w istniejącym — to samo `<Obszar>Layout.tsx` już
  to załatwia), owiń go w `ProtectedRoute` tak samo.
- Dodatkowe sprawdzenie specyficzne dla obszaru (np. `AdminLayout`'owe "czy to admin",
  wykryte po `403`/`ExceptionUuid.FORBIDDEN`) idzie **po** `ProtectedRoute`, nie przed
  ani zamiast — inaczej brak sesji w ogóle (`401`) nie zostanie złapany przez logikę
  liczącą tylko na `403` (to był realny błąd w `AdminLayout`, patrz `CHANGELOG.md`
  Część 11).
- `ProtectedRoute` przekierowuje na `/login` przy błędzie sesji; `HomePage` ma własne,
  nieco inne zachowanie (chwilowe wyrenderowanie przed przekierowaniem) — nie
  ujednolicaj tego bez konkretnego powodu, to świadomie inny przypadek ("stale
  session powinna na chwilę coś pokazać", nie natychmiastowy bounce).

### Stałe

- Magic stringi/liczby wynoś do nazwanych stałych.
- Domyślnie `as const` (tak jak `ApiEndpoints`), nie TS `enum` — chyba że coś 1:1
  odzwierciedla enum z backendu (patrz "Error handling" niżej) i sama nazwa `enum`
  czyni to czytelniejszym.
- Ten sam literal (string/number) powtórzony w więcej niż jednym miejscu dla tego
  samego znaczenia (np. lista ról `["ROLE_GUEST", "ROLE_USER", "ROLE_ADMIN"]`) idzie
  do jednej nazwanej stałej (`as const satisfies readonly T[]`, patrz `USER_ROLES`
  w `roleLabels.ts`), a nie osobnego literału przy każdym użyciu (np. w `z.enum(...)`).
  Dwie stałe, które przypadkiem mają tę samą wartość, ale znaczą co innego, zostają
  osobne.

### Importy

- Jawne, statyczne importy jako domyślny wybór.
- `React.lazy(() => import(...))` jest w porządku tam, gdzie to celowy code-splitting
  na poziomie trasy — to inna kategoria niż "niejawny/dynamiczny import czegoś, co
  równie dobrze mogłoby być zwykłym importem na górze pliku".
- Kolizja nazw → alias (`import { User as ApiUser }`), nie zgadywanie z kontekstu.

### Logi (`console.*`)

Nie wołaj `console.log`/`.info`/`.warn`/`.error` bezpośrednio — zawsze przez `logger` z
`src/libs/logger.ts`. Sterowane zmiennymi środowiskowymi (`VITE_ENABLE_DEBUG_LOGS`,
`VITE_LOG_LEVEL`, patrz `.env.example`): domyślnie logi widać w dev, na buildzie
produkcyjnym są wyciszone. Patrz też sekcja "Error handling" niżej.

### Toasty

Powiadomienia (`react-toastify`) idą przez `toastUtil` (`src/libs/toastUtil.ts`), nie
bezpośrednio przez `toast.success(...)` z biblioteki — ujednolica wygląd (tytuł per typ,
`theme: "colored"`) i obsługuje `sessionStorage`-owy "pending toast" po
reload/nawigacji (`showToastAndReload`, `showToastAndNavigateToLogin`). Nawigacja poza
komponentem Reacta (np. w interceptorze `httpClient`) idzie przez `navigationUtil`
(`src/libs/navigationUtil.ts`) — `useNavigate` jest dostępny tylko wewnątrz drzewa
routera, `navigationUtil.setNavigate` podpina go raz w `RootLayout`.

### Komentarze

Minimum. Tylko tam, gdzie logika jest nieoczywista albo niesie kontekst biznesowy,
którego nie widać z kodu — nie przy każdej funkcji "na wszelki wypadek".

### Teksty (i18n)

Żadnych tekstów wpisanych na sztywno w JSX. Wszystko idzie przez `useTranslation()`
(`react-i18next`) i klucz — `t("sekcja.klucz")` — nigdy `<p>Jakiś tekst</p>`. Katalog
tekstów: `src/locales/pl.ts`, inicjalizacja: `src/libs/i18n.ts` (importowane raz w
`main.tsx`, i osobno w `test-setup.ts` pod testy). Kod spoza drzewa komponentów (np.
`toastUtil.ts`) woła `i18n.t(...)` bezpośrednio na zaimportowanej instancji zamiast
przez hook.

Schematy walidacji `zod` (patrz "Formularze" niżej) są definiowane na poziomie modułu,
poza komponentem — `useTranslation()` tam nie zadziała — więc, tak jak `toastUtil.ts`,
wołają `i18n.t(...)` bezpośrednio na zaimportowanej instancji (`import i18n from
"../libs/i18n"`) zamiast przez hook. Klucze idą do osobnej sekcji `validation` w
`pl.ts`, nie wpisuje się ich wprost jako literałów — to samo dotyczy
`ErrorBoundary.tsx` (patrz "Error handling" niżej), z tego samego powodu (klasowy
komponent, `useTranslation()` niedostępny).

Analogiczny mechanizm istnieje po stronie backendu (Symfony Translator,
`backend/translations/*.pl.yaml`, patrz `backend/README.md`) — API też nie zwraca
sztywnych stringów, tylko przetłumaczone teksty po kluczu.

### Formularze (`react-hook-form` + `zod`)

Docelowy wzorzec dla każdego formularza z realną walidacją (nie tylko HTML-owym
`required`) — `LoginPage.tsx` jako pierwszy przykład, dziś też `NoteForm`,
`FileUploadForm`, `UnlockItemForm`, `AccessKeyPanel`:

```ts
// Na poziomie modułu — patrz sekcja "Teksty (i18n)" wyżej: i18n.t(...) bezpośrednio,
// nie useTranslation().
import i18n from "../../../../libs/i18n";

const schema = z.object({
  categoryId: z.coerce.number().int().positive(i18n.t("validation.selectCategory")),
  content: z.string().min(1, i18n.t("validation.noteContentRequired")),
});
```

- **`z.coerce.number()` (i inne `z.coerce.*`) mają inny typ wejścia niż wyjścia** —
  `<select>`/`<input>` produkują string/`unknown`, schemat zwraca `number`. Samo
  `z.infer<typeof schema>` daje typ *wyjściowy* i `useForm<typeof schema>` się nie
  skompiluje (błąd o niezgodności `Resolver`). Zamiast tego:

  ```ts
  type FormInput = z.input<typeof schema>; // to, co idzie do register()
  type FormValues = z.output<typeof schema>; // to, co dostaje onSubmit

  const { register, handleSubmit } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
  });
  ```

- Input plikowy (`<input type="file">`) nie da się sensownie zarejestrować przez
  `register()` jako kontrolowane pole zoda — trzyma się go w osobnym `useState<File |
  null>`, a `z.instanceof(File, { message: "..." })` woła się ręcznie
  (`schema.safeParse(file)`) w `onSubmit`, nie przez resolver — patrz
  `FileUploadForm.tsx`.
- Błąd z backendu (nie walidacja frontu) idzie do `setError("root", { message })`,
  wyświetlany jako `errors.root.message` — tak jak `LoginPage` robi to dla `401`.
  Patrz też "422 niesie listę `violations`" w sekcji "Error handling" niżej — to
  osobny, bardziej precyzyjny przypadek (błąd na konkretnym polu, nie ogólny).

---

## Error handling

Backend ma **jeden, stały kształt błędu** na wszystkich endpointach (patrz
`ExceptionSubscriber` + `ExceptionModel`) — to ułatwia obsługę błędów na froncie
bardziej niż zgadywanie po treści wiadomości:

```json
{
  "status": 401,
  "title": "Unauthorized",
  "detail": "...",
  "context": { "uuid": "b3d2f6a1-7c4e-4f6b-8a99-0d1e2c3b4a55" }
}
```

`context.uuid` to jedna z wartości `ExceptionUuidEnum` (`BAD_REQUEST`, `UNAUTHORIZED`,
`FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `CONFLICT`, `UNPROCESSABLE_CONTENT`,
`TOO_MANY_REQUESTS`, `INTERNAL_SERVER`) — pattern-matchuj po tym, nie po `status` samym
w sobie ani po treści `detail` (tekst dla człowieka, może się zmienić bez ostrzeżenia).
Dotyczy to też błędów, których backend sam nie rzuca (nieznana trasa, zła metoda HTTP)
— `ExceptionSubscriber` opakowuje je w ten sam kształt, nigdy nie zwraca gołego błędu
frameworka.

- `AxiosBaseQueryError`/błąd z `httpClient` zawsze niesie `data` w tym kształcie przy
  odpowiedzi z naszego API — `src/libs/apiError.ts` to ten współdzielony typ
  (`ApiErrorBody`) plus `ExceptionUuid` (odpowiednik `ExceptionUuidEnum` z backendu),
  `getApiErrorBody(error)`/`getApiErrorUuid(error)`/`isApiError(error, uuid)`. Używaj
  tego zamiast każdorazowo rzutować `error.data` ręcznie i zamiast porównywać `status`
  albo `detail` wprost.
- **422 (`UnprocessableContentException`) niesie listę `violations`**
  (`{ propertyPath, message, code }[]`) — to mapuje się wprost na
  `setError(propertyPath, { message })` z `react-hook-form`. Docelowy wzorzec dla
  formularzy: po 422 przejdź po `violations` i ustaw błąd na każdym polu z osobna,
  zamiast jednego ogólnego komunikatu (tak jak dziś robi to `LoginPage` dla 401 —
  tam wystarczy jeden komunikat, bo to nie błąd walidacji pola).
- Nigdy nie wołaj `console.log`/`console.info`/`console.warn`/`console.error` wprost —
  używaj `logger` z `src/libs/logger.ts` (`logger.error(...)` zamiast `console.error(...)`
  itd.). Wrapper wycisza logi na produkcji (domyślnie logi lecą tylko w dev, patrz
  `VITE_ENABLE_DEBUG_LOGS`/`VITE_LOG_LEVEL` w `.env.example`) — gołe `console.*`
  ominęłoby to wyciszenie. `logger.error` w `catch` jest OK na tym etapie (nie mamy
  jeszcze serwisu do zbierania błędów) — ale nie połykaj błędu bez żadnego śladu.
- Każda funkcja `async`, która woła API, obsługuje błąd — nie zostawiaj
  nieobsłużonego rejection.
- **Wysyłając `FormData` (upload pliku), nie ustawiaj `Content-Type` samodzielnie i
  nie polegaj na domyślnym `httpClient`'owym `application/json`** — z tym nagłówkiem
  axios *serializuje `FormData` do JSON-a* zamiast wysłać multipart (sprawdzone
  wprost w źródłach axios, `transformRequest` patrzy na nagłówek, nie na typ
  payloadu — realny błąd znaleziony i naprawiony w Części 11). Wołaj przez
  `src/libs/httpMethods.ts` (`get/post/put/patch/del`) albo przez `axiosBaseQuery`
  (RTK Query) — oba już czyszczą `Content-Type` dla `FormData`, żeby przeglądarka
  sama dopisała boundary. Nie wołaj `httpClient(...)` bezpośrednio z `FormData` w
  body pomijając te dwa miejsca.
- `ExceptionUuid`/`ApiErrorBody` w `libs/apiError.ts` są dziś przepisane ręcznie z
  backendowego `ExceptionUuidEnum` — nadal warto rozważyć wygenerowanie ich wprost z
  OpenAPI (`nelmio/api-doc-bundle`, `/api/doc.json`), żeby nie trzeba było ręcznie
  pilnować zgodności przy każdej zmianie po stronie backendu.

### `ErrorBoundary`

`src/providers/ErrorBoundary.tsx` łapie błędy renderowania gdziekolwiek niżej w
drzewie (`componentDidCatch`/`getDerivedStateFromError` — jedyny sposób to zrobić,
nie ma hookowego odpowiednika) i pokazuje przetłumaczony fallback zamiast białego
ekranu po odmontowaniu całej aplikacji. Owinięty wokół `<Provider>`/`<RouterProvider>`
w `App.tsx` — jeden `ErrorBoundary` na całą aplikację, nie po jednym na obszar/moduł,
chyba że konkretny widok (np. duży, ryzykowny fragment trzeciej strony) uzasadni
własny, bardziej lokalny.

**To NIE łapie błędów z event handlerów ani z `async` callbacków** — to ograniczenie
Reacta, nie tego komponentu. Te przypadki nadal muszą mieć własny `try/catch` (patrz
"Każda funkcja `async`, która woła API, obsługuje błąd" wyżej) — `ErrorBoundary` jest
ostatnią linią obrony dla tego, co nieobsłużony `try/catch` by przegapił, nie
zamiennikiem dla obsługi błędów w komponentach.
