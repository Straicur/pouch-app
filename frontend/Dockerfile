# syntax=docker/dockerfile:1
FROM node:22-alpine AS base
WORKDIR /app

# Etap deweloperski: vite dev server z HMR (źródła montowane przez docker-compose).
FROM base AS dev
COPY package*.json ./
RUN npm install
COPY . .
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host"]

# --- Build produkcyjny ---
FROM base AS build
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Etap produkcyjny: statyczny build serwowany przez nginx.
FROM nginx:stable-alpine AS prod
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
