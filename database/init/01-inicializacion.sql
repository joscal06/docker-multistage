-- -----------------------------------------------------------------------------
-- Script de inicialización de MySQL
-- Montado en /docker-entrypoint-initdb.d/. La imagen oficial lo ejecuta UNA
-- sola vez, cuando el volumen db_data está vacío, sobre la base de datos
-- definida en MYSQL_DATABASE. Las tablas de la aplicación las crean las
-- migraciones de Laravel al arrancar el contenedor api.
-- -----------------------------------------------------------------------------

ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla informativa: permite comprobar que el script se ejecutó
CREATE TABLE IF NOT EXISTS ecosistema_info (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave      VARCHAR(64)  NOT NULL UNIQUE,
    valor      VARCHAR(255) NOT NULL,
    creado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO ecosistema_info (clave, valor) VALUES
    ('inicializado_por', '/docker-entrypoint-initdb.d/01-inicializacion.sql'),
    ('version_mysql', VERSION()),
    ('charset', @@character_set_database);
