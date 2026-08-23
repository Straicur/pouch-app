// CONSTANT_CASE on purpose — see biome.json overrides.
export const ApiEndpoints = {
  LOGIN: "/api/login",
  LOGOUT: "/api/logout",
  REFRESH_TOKEN: "/api/auth/token/refresh",
  TEST: "/api/test",
} as const;

// A 401 here must not trigger a refresh-and-retry (wrong credentials / dead refresh token).
export const NO_REFRESH_ENDPOINTS: readonly string[] = [ApiEndpoints.LOGIN, ApiEndpoints.REFRESH_TOKEN];
