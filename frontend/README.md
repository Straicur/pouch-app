# Pouch — frontend

React 18 + TypeScript + Vite SPA for the Pouch app.

## Stack

- Vite, React 18, TypeScript
- ESLint

## Getting started (without Docker)

```
npm install
npm run dev
```

The dev server proxies `/api/*` to the backend (see `vite.config.ts`,
`VITE_API_PROXY_TARGET`), so it expects the backend running at
`http://localhost:8111` by default (see `../backend`).

## With Docker

From the repo root:

```
make up
```

See the root `README.md` / `Makefile` for the full-stack workflow.

## Common tasks

| Command | Description |
| --- | --- |
| `npm run dev` | Start the Vite dev server |
| `npm run build` | Type-check and build for production |
| `npm run preview` | Preview the production build locally |
| `npm run lint` | Run ESLint |
