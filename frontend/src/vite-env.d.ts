/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL: string;
  readonly VITE_ENABLE_DEBUG_LOGS?: string;
  readonly VITE_LOG_LEVEL?: string;
}
