# E-commerce API Segura con Swagger Completo

**Autor:** José Manuel Calderón González

API RESTful construida con **Laravel 12** para un e-commerce básico: gestión de clientes, catálogo de
productos, órdenes de compra y procesamiento de pagos con **Stripe**. Documentada completamente con
**Swagger/OpenAPI** y protegida con autenticación **JWT**.

## 📚 Caso de estudio

El sistema cubre el flujo completo de una tienda en línea:

1. Un cliente se registra y se autentica (JWT).
2. Navega el catálogo público de productos (sin necesidad de autenticarse).
3. Crea una orden de compra a partir de uno o varios productos y cantidades.
4. Paga la orden mediante Stripe (Payment Intents).
5. Consulta su historial de compras y el estado de cada pago.

Un administrador (cualquier usuario autenticado, en esta versión) puede además gestionar el catálogo:
crear, editar y eliminar productos.

## 🏗️ Arquitectura y buenas prácticas aplicadas

- **Controladores delgados** (`app/Http/Controllers/Api`): solo coordinan petición → servicio/modelo → respuesta.
- **Separación de responsabilidades**:
  - Validación → Form Requests (`app/Http/Requests`), con mensajes descriptivos en español.
  - Formato de salida → API Resources (`app/Http/Resources`).
  - Respuestas homogéneas → `App\Http\Responses\ApiResponse` (`{success, message, data}` / `{success, message, errors}`).
  - Integración con Stripe encapsulada en `App\Services\StripePaymentService` (nada de lógica de Stripe en los controladores).
- **Manejo de errores centralizado** (`bootstrap/app.php`): toda excepción bajo `/api/*` responde siempre
  en JSON con el código HTTP correcto (401, 403, 404, 409, 422, 502, 500), sin importar el header `Accept`
  del cliente.
- **Autenticación JWT** (`tymon/jwt-auth`, guard `api`), con endpoints de registro, login,
  logout, refresh y perfil (`/me`).
- **Base de datos relacional (MySQL)**: migraciones versionadas, claves foráneas con `constrained()`,
  índices en columnas de filtrado frecuente (`status`, `category`, `is_active`), *casts* de atributos
  (decimales, booleanos, JSON).
- **Transacciones atómicas**: la creación de una orden bloquea (`lockForUpdate`) y descuenta el stock de
  cada producto dentro de una única transacción de base de datos; si algún producto no tiene stock
  suficiente, toda la operación se revierte y responde `409 Conflict`.
- **Stripe end-to-end**: creación de Payment Intents, endpoint de verificación de estado, y **webhook**
  (`payment_intent.succeeded` / `payment_intent.payment_failed` / `.canceled`) con verificación de firma,
  que actualiza automáticamente el estado del pago y de la orden.
- **Documentación Swagger/OpenAPI completa** (`darkaonline/l5-swagger` + atributos PHP 8 `#[OA\...]`):
  todos los endpoints, esquemas de request/response, códigos de estado y el esquema de seguridad
  `bearerAuth` para probar la API autenticada desde la propia UI.
- **Seeders realistas**: 20 productos de electrónica/tecnología con SKU, categoría, precio y stock.
- **Tests automatizados** (`tests/Feature/Api`): cubren registro/login, CRUD de productos, creación de
  órdenes (incluyendo descuento de stock y conflicto por falta de inventario) y control de acceso entre
  clientes distintos.

## 📦 Requisitos

- PHP >= 8.2 con extensiones `pdo_mysql`, `mbstring`, `openssl`, **`sodium`** (requerida por el paquete
  JWT; en XAMPP suele venir deshabilitada — habilítala descomentando `extension=sodium` en `php.ini`).
- Composer 2.x
- MySQL/MariaDB (por ejemplo, el que trae XAMPP)
- Una cuenta gratuita de [Stripe](https://dashboard.stripe.com/register) en **modo de prueba** (opcional
  para explorar el resto de la API; solo se necesita para probar el flujo de pago real).

## 🚀 Instalación

```bash
# 1. Clonar el repositorio
git clone https://github.com/joscal06/swaggerapi.git ecommerce
cd ecommerce

# 2. Instalar dependencias de PHP
composer install

# 3. Copiar el archivo de entorno
cp .env.example .env
php artisan key:generate

# 4. Configurar la base de datos (ver sección siguiente) y luego migrar + sembrar
php artisan migrate --seed

# 5. Generar el secreto JWT
php artisan jwt:secret

# 6. Generar la documentación Swagger
php artisan l5-swagger:generate

# 7. Levantar el servidor de desarrollo
php artisan serve
```

La API quedará disponible en `http://127.0.0.1:8000/api/...` y la documentación Swagger en
`http://127.0.0.1:8000/api/documentation`.

### Configurar la base de datos (MySQL)

1. Crea la base de datos (vía phpMyAdmin o consola):

   ```sql
   CREATE DATABASE ecommerce_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. En `.env`, ajusta las credenciales de conexión:

   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ecommerce_api
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   > **Nota (XAMPP en Windows):** si además tienes instalado un MySQL independiente (por ejemplo, el
   > servicio "MySQL80"), es posible que ocupe el puerto 3306 y el MySQL de XAMPP quede corriendo en
   > otro puerto (revisa `xampp/mysql/bin/my.ini`, directiva `port`). Ajusta `DB_PORT` según corresponda.

3. Ejecuta `php artisan migrate --seed`.

### Ejecutar dentro de XAMPP (Apache)

1. Copia/clona el proyecto dentro de `C:\xampp\htdocs\ecommerce`.
2. Ajusta `APP_URL` en `.env` a `http://localhost/ecommerce/public`.
3. Con Apache y MySQL corriendo desde el Panel de Control de XAMPP, la API queda accesible en:
   `http://localhost/ecommerce/public/api/...` y la documentación en
   `http://localhost/ecommerce/public/api/documentation`
   (XAMPP trae `mod_rewrite` habilitado por defecto, necesario para las rutas "limpias" de Laravel).

### Configurar Stripe (opcional, para probar pagos)

1. Crea una cuenta en [Stripe](https://dashboard.stripe.com/register) y activa el **modo de prueba**.
2. En el dashboard, ve a *Developers > API keys* y copia:
   - **Publishable key** (`pk_test_...`) → `STRIPE_KEY`
   - **Secret key** (`sk_test_...`) → `STRIPE_SECRET`
3. Para recibir el webhook en local, instala el [Stripe CLI](https://docs.stripe.com/stripe-cli) y ejecuta:

   ```bash
   stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
   ```

   El comando imprime un `whsec_...`: cópialo en `STRIPE_WEBHOOK_SECRET`.
4. Usa una [tarjeta de prueba](https://docs.stripe.com/testing) (p. ej. `4242 4242 4242 4242`, cualquier
   fecha futura y CVC) para confirmar el pago desde tu frontend/Postman con Stripe.js o el SDK móvil,
   usando el `client_secret` devuelto por `POST /api/orders/{order}/pay`.

Si aún no configuras Stripe, el resto de la API (auth, catálogo, órdenes) funciona igual; el endpoint de
pago simplemente responderá `502` con un mensaje descriptivo indicando que no se pudo contactar a Stripe.

## 📖 Documentación de la API (Swagger)

Disponible en **`/api/documentation`** una vez el servidor está corriendo. Incluye todos los endpoints,
esquemas de petición/respuesta, códigos de estado posibles y un botón **Authorize** para pegar el token
JWT (`Bearer {token}`) y probar los endpoints protegidos directamente desde la UI.

Para regenerar la documentación tras modificar los atributos `#[OA\...]`:

```bash
php artisan l5-swagger:generate
```

## 🧪 Cómo probar la API

### Opción A — Swagger UI (recomendada, no requiere instalar nada)

1. Levanta el servidor (`php artisan serve`, o Apache/XAMPP como se explicó arriba).
2. Abre **`/api/documentation`** en el navegador (p. ej. `http://127.0.0.1:8000/api/documentation` o
   `http://localhost/ecommerce/public/api/documentation`).
3. Expande **Auth → POST /api/auth/login**, haz clic en **Try it out** y usa el cliente de prueba:
   ```json
   { "email": "cliente@demo.test", "password": "password" }
   ```
   Ejecuta y copia el `token` de la respuesta.
4. Haz clic en el botón **Authorize** (arriba a la derecha, con el candado), pega el token y confirma.
   A partir de aquí, todos los endpoints protegidos se pueden probar ya autenticado, directamente desde
   la UI, sin herramientas adicionales.
5. Sugerencia de flujo para probar el caso de uso completo:
   - `GET /api/products` → explora el catálogo (público).
   - `POST /api/orders` con `items: [{ "product_id": 1, "quantity": 2 }]` → crea una orden.
   - `POST /api/orders/{order}/pay` → genera el Payment Intent de Stripe para esa orden.
   - `GET /api/orders` → revisa el historial de compras del cliente.

### Opción B — curl / Postman

```bash
# Login con el usuario de prueba
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"cliente@demo.test","password":"password"}'

# Catálogo público (no requiere token)
curl http://127.0.0.1:8000/api/products

# Crear una orden (usando el token devuelto por el login)
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -d '{"items":[{"product_id":1,"quantity":2}]}'
```

Si prefieres Postman, puedes importar directamente la especificación OpenAPI generada
(`storage/api-docs/api-docs.json`, o `http://127.0.0.1:8000/docs?api-docs.json` con el servidor
corriendo) vía *Import > File/Link* para tener una colección completa con todos los endpoints.

### Probar el flujo de pago real con Stripe

Configura `STRIPE_KEY`/`STRIPE_SECRET` en `.env` (ver sección de Stripe más abajo). Sin esas claves,
`POST /api/orders/{order}/pay` responde `502` con un mensaje descriptivo — es el comportamiento esperado
y no un error de la API. Con las claves configuradas, usa el `client_secret` de la respuesta junto con
Stripe.js/el SDK y una [tarjeta de prueba](https://docs.stripe.com/testing) (`4242 4242 4242 4242`) para
confirmar el pago, y el Stripe CLI para que el webhook llegue a tu entorno local.

## 🔌 Resumen de endpoints

| Método | Endpoint                       | Auth | Descripción                                   |
|--------|---------------------------------|:----:|------------------------------------------------|
| POST   | `/api/auth/register`            |  ❌  | Registro de cliente (devuelve token JWT)        |
| POST   | `/api/auth/login`                |  ❌  | Login (devuelve token JWT)                      |
| POST   | `/api/auth/logout`               |  ✅  | Invalida el token actual                        |
| POST   | `/api/auth/refresh`              |  ✅  | Refresca el token JWT                           |
| GET    | `/api/auth/me`                   |  ✅  | Perfil del cliente autenticado                  |
| GET    | `/api/products`                  |  ❌  | Catálogo público (filtros: `category`, `search`)|
| GET    | `/api/products/{product}`        |  ❌  | Detalle de un producto                          |
| POST   | `/api/products`                  |  ✅  | Crear producto                                  |
| PUT    | `/api/products/{product}`        |  ✅  | Actualizar producto                             |
| DELETE | `/api/products/{product}`        |  ✅  | Eliminar producto (409 si tiene órdenes)        |
| GET    | `/api/orders`                    |  ✅  | Historial de compras del cliente autenticado    |
| POST   | `/api/orders`                    |  ✅  | Crear una orden a partir de un carrito          |
| GET    | `/api/orders/{order}`            |  ✅  | Detalle de una orden propia                     |
| POST   | `/api/orders/{order}/pay`        |  ✅  | Crear Payment Intent de Stripe para la orden     |
| GET    | `/api/payments/{payment}`        |  ✅  | Consultar/sincronizar el estado de un pago       |
| POST   | `/api/stripe/webhook`            |  ❌  | Webhook de eventos de Stripe (firma verificada) |

## ✅ Ejecutar las pruebas automatizadas

Las pruebas usan una base de datos SQLite en memoria (no tocan tu base de datos MySQL de desarrollo):

```bash
php artisan test
```

## 🗂️ Estructura relevante del proyecto

```
app/
├── Http/
│   ├── Controllers/Api/     # AuthController, ProductController, OrderController, PaymentController
│   ├── Requests/             # Form Requests (validación)
│   ├── Resources/            # Transformación de modelos a JSON
│   └── Responses/ApiResponse.php
├── Models/                   # User, Product, Order, OrderItem, Payment
├── Services/StripePaymentService.php
└── OpenApi/SharedSchemas.php # Esquemas Swagger compartidos (ErrorResponse, ValidationError)

database/
├── migrations/               # products, orders, order_items, payments (+ users con phone/address)
├── factories/                 # Para tests (Product, Order, User)
└── seeders/                  # ProductSeeder (20 productos) + DatabaseSeeder

routes/api.php                 # Todas las rutas de la API
bootstrap/app.php               # Middleware, guard JWT y manejo centralizado de excepciones
```

## 🔑 Usuario de prueba

El seeder crea un cliente de prueba para probar el login sin registrarte primero:

- **email:** `cliente@demo.test`
- **password:** `password`
