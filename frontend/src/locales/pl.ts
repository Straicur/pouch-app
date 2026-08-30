// The one place every user-facing string in the app lives — components pull
// text via useTranslation()'s t("key"), never inline literals (see
// docs/codestyle/FRONTEND.md). Form validation messages (zod schemas) are the
// deliberate exception: they're defined at module scope, outside any
// component that could call the hook, so they stay inline for now.
const pl = {
  common: {
    appName: "Pouch",
    loading: "Ładowanie…",
  },
  auth: {
    loginTitle: "Zaloguj się",
    loginButton: "Zaloguj się",
    loginButtonLoading: "Logowanie…",
    emailLabel: "E-mail",
    passwordLabel: "Hasło",
    invalidCredentials: "Nieprawidłowy e-mail lub hasło.",
    genericError: "Coś poszło nie tak. Spróbuj ponownie.",
    logoutButton: "Wyloguj się",
  },
  home: {
    loggedInAs: "Zalogowano jako: {{email}}",
    connectionError: "Nie udało się połączyć z backendem.",
    viewItemsLink: "Zobacz itemy",
  },
  items: {
    homeLink: "Strona główna",
    fetchError: "Nie udało się pobrać itemów.",
    empty: "Brak itemów do pokazania.",
    processing: "Przetwarzanie…",
    processingError: "Błąd przetwarzania",
    type: {
      file: "Plik",
      url: "Link",
      photo: "Zdjęcie",
      note: "Notatka",
    },
    searchPlaceholder: "Szukaj po nazwie, tagach, treści, OCR…",
    favoritesOnly: "Tylko ulubione",
    tagFilterPlaceholder: "Filtruj po tagu",
    clearFilters: "Wyczyść filtry",
  },
  tags: {
    markFavorite: "Dodaj do ulubionych",
    unmarkFavorite: "Usuń z ulubionych",
    favoriteError: "Nie udało się zaktualizować ulubionych.",
    editTags: "Edytuj tagi",
    tagsPlaceholder: "tagi oddzielone przecinkami",
    save: "Zapisz",
    cancel: "Anuluj",
    saving: "Zapisywanie…",
    updateError: "Nie udało się zapisać tagów.",
    noTags: "Brak tagów",
  },
  notes: {
    addTitle: "Dodaj notatkę",
    categoryLabel: "Kategoria",
    nameLabel: "Nazwa (opcjonalnie)",
    contentLabel: "Treść (markdown)",
    submit: "Dodaj notatkę",
    submitting: "Dodawanie…",
    createError: "Nie udało się dodać notatki.",
    edit: "Edytuj",
    save: "Zapisz",
    cancel: "Anuluj",
    saving: "Zapisywanie…",
    updateError: "Nie udało się zapisać notatki.",
  },
  toast: {
    success: "Sukces",
    error: "Błąd",
    warning: "Ostrzeżenie",
    info: "Informacja",
  },
} as const;

export default pl;
