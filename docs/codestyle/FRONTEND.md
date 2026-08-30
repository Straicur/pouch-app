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

Bez SASS/SCSS na razie — zwykły CSS. Osobna decyzja do podjęcia później, nie coś
przyjęte mimochodem tym dokumentem.

## Jak uruchomić

| Komenda | Co robi |
| --- | --- |
| `make lint` | biome lint (dry-run) |
| `make lint-fix` | biome lint --write |
| `npm run check` / `check:fix` | lint + format razem (dry-run / write) |
| `npm test` | vitest run |

---

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

### Stałe

- Magic stringi/liczby wynoś do nazwanych stałych.
- Domyślnie `as const` (tak jak `ApiEndpoints`), nie TS `enum` — chyba że coś 1:1
  odzwierciedla enum z backendu (patrz "Error handling" niżej) i sama nazwa `enum`
  czyni to czytelniejszym.

### Importy

- Jawne, statyczne importy jako domyślny wybór.
- `React.lazy(() => import(...))` jest w porządku tam, gdzie to celowy code-splitting
  na poziomie trasy — to inna kategoria niż "niejawny/dynamiczny import czegoś, co
  równie dobrze mogłoby być zwykłym importem na górze pliku".
- Kolizja nazw → alias (`import { User as ApiUser }`), nie zgadywanie z kontekstu.

### Logi (`console.*`)

Nie wołaj `console.log`/`.info`/`.warn`/`.error` bezpośrednio — zawsze przez `logger` z
`src/lib/logger.ts`. Sterowane zmiennymi środowiskowymi (`VITE_ENABLE_DEBUG_LOGS`,
`VITE_LOG_LEVEL`, patrz `.env.example`): domyślnie logi widać w dev, na buildzie
produkcyjnym są wyciszone. Patrz też sekcja "Error handling" niżej.

### Toasty

Powiadomienia (`react-toastify`) idą przez `toastUtil` (`src/lib/toastUtil.ts`), nie
bezpośrednio przez `toast.success(...)` z biblioteki — ujednolica wygląd (tytuł per typ,
`theme: "colored"`) i obsługuje `sessionStorage`-owy "pending toast" po
reload/nawigacji (`showToastAndReload`, `showToastAndNavigateToLogin`). Nawigacja poza
komponentem Reacta (np. w interceptorze `httpClient`) idzie przez `navigationUtil`
(`src/lib/navigationUtil.ts`) — `useNavigate` jest dostępny tylko wewnątrz drzewa
routera, `navigationUtil.setNavigate` podpina go raz w `RootLayout`.

### Komentarze

Minimum. Tylko tam, gdzie logika jest nieoczywista albo niesie kontekst biznesowy,
którego nie widać z kodu — nie przy każdej funkcji "na wszelki wypadek".

### Teksty (i18n)

Żadnych tekstów wpisanych na sztywno w JSX. Wszystko idzie przez `useTranslation()`
(`react-i18next`) i klucz — `t("sekcja.klucz")` — nigdy `<p>Jakiś tekst</p>`. Katalog
tekstów: `src/locales/pl.ts`, inicjalizacja: `src/lib/i18n.ts` (importowane raz w
`main.tsx`, i osobno w `test-setup.ts` pod testy). Kod spoza drzewa komponentów (np.
`toastUtil.ts`) woła `i18n.t(...)` bezpośrednio na zaimportowanej instancji zamiast
przez hook.

Wyjątek: schematy walidacji `zod` (np. w `LoginPage.tsx`) są definiowane na poziomie
modułu, poza komponentem — `useTranslation()` tam nie zadziała — więc ich komunikaty
zostają wpisane wprost, ze świadomym komentarzem tłumaczącym dlaczego.

Analogiczny mechanizm istnieje po stronie backendu (Symfony Translator,
`backend/translations/*.pl.yaml`, patrz `backend/README.md`) — API też nie zwraca
sztywnych stringów, tylko przetłumaczone teksty po kluczu.

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
  odpowiedzi z naszego API — warto dodać współdzielony typ tego envelope'u zamiast
  każdorazowo rzutować `error.data` ręcznie.
- **422 (`UnprocessableContentException`) niesie listę `violations`**
  (`{ propertyPath, message, code }[]`) — to mapuje się wprost na
  `setError(propertyPath, { message })` z `react-hook-form`. Docelowy wzorzec dla
  formularzy: po 422 przejdź po `violations` i ustaw błąd na każdym polu z osobna,
  zamiast jednego ogólnego komunikatu (tak jak dziś robi to `LoginPage` dla 401 —
  tam wystarczy jeden komunikat, bo to nie błąd walidacji pola).
- Nigdy nie wołaj `console.log`/`console.info`/`console.warn`/`console.error` wprost —
  używaj `logger` z `src/lib/logger.ts` (`logger.error(...)` zamiast `console.error(...)`
  itd.). Wrapper wycisza logi na produkcji (domyślnie logi lecą tylko w dev, patrz
  `VITE_ENABLE_DEBUG_LOGS`/`VITE_LOG_LEVEL` w `.env.example`) — gołe `console.*`
  ominęłoby to wyciszenie. `logger.error` w `catch` jest OK na tym etapie (nie mamy
  jeszcze serwisu do zbierania błędów) — ale nie połykaj błędu bez żadnego śladu.
- Każda funkcja `async`, która woła API, obsługuje błąd — nie zostawiaj
  nieobsłużonego rejection.
- Warto rozważyć wygenerowanie typów tego envelope'u i `ExceptionUuidEnum` wprost z
  OpenAPI, które już generuje `nelmio/api-doc-bundle` (`/api/doc.json`) — zamiast
  ręcznie przepisywać je po stronie frontendu i pilnować zgodności ręcznie.

---

## Do ustalenia

- Gdzie mieszkają typy współdzielone (`src/types/` czy kolokowane przy
  `store/api/*.ts`, jak dziś) — część rozmowy o rozbiciu folderów, jeszcze nie
  rozstrzygnięta.
- SASS vs zwykły CSS na dłuższą metę.
