# syntax=docker/dockerfile:1

# =============================================================================
# Frontend · Voltio (Next.js 16, App Router)
# Build multi-stage: base → deps → builder → prod-deps → runner
# =============================================================================

# Next.js 16 exige Node.js >= 20.9. La actividad recomienda node:20-alpine;
# se usa 22-alpine (LTS activa) porque Node 20 dejó de recibir parches de
# seguridad en abril de 2026. Se puede cambiar sin editar el archivo:
#   docker build --build-arg NODE_VERSION=20-alpine .
ARG NODE_VERSION=22-alpine

# -----------------------------------------------------------------------------
# Etapa 1 · base: imagen y variables comunes a todas las etapas
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION} AS base
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1

# -----------------------------------------------------------------------------
# Etapa 2 · deps: todas las dependencias (incluye devDependencies para compilar)
# Solo se copian package.json y package-lock.json: mientras no cambien, Docker
# reutiliza esta capa y no vuelve a ejecutar `npm ci`.
# -----------------------------------------------------------------------------
FROM base AS deps
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund

# -----------------------------------------------------------------------------
# Etapa 3 · builder: compila la aplicación con `next build`
# -----------------------------------------------------------------------------
FROM base AS builder
# Las variables NEXT_PUBLIC_* se incrustan en el JavaScript del navegador
# durante el build, por eso llegan como argumento de build y no en runtime.
# Es una llave PUBLICABLE (pk_test_...), nunca la secreta.
ARG NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=""
ENV NODE_ENV=production \
    NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=${NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY}
COPY --from=deps /app/node_modules ./node_modules
COPY . .
# .next/cache solo acelera builds posteriores; no se usa en tiempo de ejecución
RUN npm run build && rm -rf .next/cache

# -----------------------------------------------------------------------------
# Etapa 4 · prod-deps: solo las dependencias de producción
# -----------------------------------------------------------------------------
FROM base AS prod-deps
COPY package.json package-lock.json ./
# El lockfile no distingue libc, así que npm instala los binarios nativos de
# glibc y de musl. Alpine usa musl: se borran las variantes glibc que no sirven.
RUN --mount=type=cache,target=/root/.npm npm ci --omit=dev --no-audit --no-fund \
 && rm -rf node_modules/@next/swc-*-gnu \
           node_modules/@img/sharp-linux-* \
           node_modules/@img/sharp-libvips-linux-*

# -----------------------------------------------------------------------------
# Etapa 5 · runner: imagen final, solo lo necesario para `next start`
# -----------------------------------------------------------------------------
FROM base AS runner
ENV NODE_ENV=production \
    PORT=3000 \
    HOSTNAME=0.0.0.0

# Archivos copiados con dueño `node` (usuario sin privilegios de la imagen oficial)
COPY --from=prod-deps --chown=node:node /app/node_modules ./node_modules
COPY --from=builder   --chown=node:node /app/package.json /app/next.config.ts ./
COPY --from=builder   --chown=node:node /app/.next ./.next
USER node

EXPOSE 3000

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=5 \
  CMD wget -qO- http://127.0.0.1:${PORT}/api/health || exit 1

# Se invoca el binario directamente (sin npm) para que Node reciba SIGTERM
CMD ["node_modules/.bin/next", "start"]
