#!/usr/bin/env sh
# Crea .env a partir de .env.example con claves aleatorias para APP_KEY,
# JWT_SECRET y las contraseñas de MySQL. No sobrescribe un .env existente.
# Uso:  ./scripts/generar-env.sh      (en Windows, desde Git Bash)
set -e
cd "$(dirname "$0")/.."

if [ -f .env ]; then
  echo ".env ya existe; no se modifica. Bórralo si quieres generarlo de nuevo."
  exit 0
fi

aleatorio() { openssl rand -base64 "$1" | tr -d '\n'; }
clave()     { openssl rand -hex 16; }

sed -e "s|^APP_KEY=.*|APP_KEY=base64:$(aleatorio 32)|" \
    -e "s|^JWT_SECRET=.*|JWT_SECRET=$(aleatorio 48)|" \
    -e "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=$(clave)|" \
    -e "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=$(clave)|" \
    .env.example > .env

echo ".env generado con claves aleatorias."
