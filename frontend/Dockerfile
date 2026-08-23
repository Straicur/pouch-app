# syntax=docker/dockerfile:1
FROM node:22-alpine AS base
WORKDIR /app
RUN chown node:node /app

# Uruchamiamy jako user `node`, nie root — inaczej pliki tworzone w kontenerze
# (przez bind mount widoczne na hoście) lądują jako root:root.
FROM base AS dev
USER node
COPY --chown=node:node package*.json ./
RUN npm install
COPY --chown=node:node . .
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
