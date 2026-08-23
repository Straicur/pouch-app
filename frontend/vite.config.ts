import react from "@vitejs/plugin-react";
import { defineConfig } from "vitest/config";

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true, // listen on 0.0.0.0 so the dev server is reachable from the Docker host
    port: 5173,
    strictPort: true,
    proxy: {
      // same-origin proxy to the backend, so cookies work without CORS.
      "/api": {
        target: process.env.VITE_API_PROXY_TARGET ?? "http://localhost:8111",
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: "jsdom",
    setupFiles: ["./src/test-setup.ts"],
    globals: true,
  },
});
