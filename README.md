# GestioRubyCristancho

Salón de belleza **Ruby Cristancho**: backend **Laravel 13 API-only** (sin Blade) + SPA **Angular 22** en `frontend/`.

- API JSON: `routes/api.php` (Sanctum SPA, permisos por ruta).
- Documentación interactiva: `http://127.0.0.1:8000/docs` (Swagger UI).
- OpenAPI generado: `http://127.0.0.1:8000/docs/openapi.json`.
- La raíz `/` responde metadatos de la API; no hay pantallas web ni rutas Blade.

## Requisitos

- PHP 8.3+ y Composer.
- Node 20+ y npm.
- MySQL (`beautysalon`); la configuración real está en `.env`.

## Backend

```powershell
composer install
Copy-Item .env.example .env   # ajusta la conexión MySQL
php artisan key:generate
php artisan migrate
php artisan serve
```

La API queda en `http://127.0.0.1:8000`. Para la cola: `php artisan queue:listen`.

## Frontend

```powershell
cd frontend
npm install
npm run dev
```

La SPA queda en `http://127.0.0.1:4200` y su proxy envía `/api` y `/sanctum` al backend. Build de producción:

```powershell
cd frontend
npm run build
```

## Pruebas

```powershell
composer run test
cd frontend; npm test
./vendor/bin/pint --test
```

Las pruebas de Laravel usan `beautysalon_testing` y `DatabaseTransactions`; no tocan la base real. Ver `AGENTS.md` para la arquitectura, permisos y reglas de inventario/cierre semanal.

## Estructura

- `app/Http/Controllers/Api/`: controladores delgados de la API.
- `app/Services/`: lógica de negocio (inventario, ventas, caja, cierres, reportes).
- `app/Support/Money.php`: aritmética con BCMath (nunca `float` para dinero).
- `resources/docs/index.html`: Swagger UI estático.
- `frontend/`: SPA Angular (login, panel, inventario, ventas, reportes, administración).
