// CONSTANT_CASE on purpose — see biome.json overrides.
export const ApiEndpoints = {
  LOGIN: "/api/login",
  LOGOUT: "/api/logout",
  REFRESH_TOKEN: "/api/auth/token/refresh",
  TEST: "/api/test",
  ITEMS: "/api/items",
  ITEM_NOTES: "/api/items/notes",
  ITEM_NOTE: (id: number) => `/api/items/${id}/note`,
  ITEM_THUMBNAIL_LINK: (id: number) => `/api/items/${id}/thumbnail-link`,
  ITEM_DOWNLOAD_LINK: (id: number) => `/api/items/${id}/download-link`,
  CATEGORIES: "/api/categories",
} as const;

// A 401 here must not trigger a refresh-and-retry (wrong credentials / dead refresh token).
export const NO_REFRESH_ENDPOINTS: readonly string[] = [ApiEndpoints.LOGIN, ApiEndpoints.REFRESH_TOKEN];
