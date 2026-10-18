# Imagen del frontend del Portal de Onboarding de Hefesto.
#
# Compilación en dos etapas: la imagen final no trae ni npm ni el código fuente, solo la
# salida de Nuxt. Versión de Node fijada, porque una versión flotante produce compilaciones
# distintas en el mismo código.

FROM node:22.11-alpine3.20 AS compilacion

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund

COPY . .
RUN npm run build

FROM node:22.11-alpine3.20 AS ejecucion

WORKDIR /app
ENV NODE_ENV=production
ENV NITRO_PORT=3000
ENV NITRO_HOST=0.0.0.0

COPY --from=compilacion /app/.output ./.output

USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]