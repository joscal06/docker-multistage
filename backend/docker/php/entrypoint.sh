#!/bin/sh
# Punto de entrada del contenedor api: prepara Laravel con la configuración
# que llega por variables de entorno y luego arranca PHP-FPM.
set -e
cd /var/www/html

for var in APP_KEY JWT_SECRET DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
  eval "value=\${$var:-}"
  if [ -z "$value" ]; then
    echo "ERROR: falta la variable de entorno $var (revisa el archivo .env)" >&2
    exit 1
  fi
done

# Las variables se conocen hasta el arranque, por eso la caché de config,
# rutas, eventos y vistas se genera aquí y no durante el build.
php artisan optimize

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  intentos=0
  until php artisan migrate --force --no-interaction; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 10 ]; then
      echo "ERROR: no fue posible migrar la base de datos" >&2
      exit 1
    fi
    echo "MySQL aún no acepta conexiones, reintentando en 3 s ($intentos/10)..."
    sleep 3
  done
fi

if [ "${SEED_DATABASE:-false}" = "true" ]; then
  # Los seeders usan updateOrCreate/firstOrCreate: se pueden repetir sin duplicar
  php artisan db:seed --force --no-interaction
fi

exec "$@"
