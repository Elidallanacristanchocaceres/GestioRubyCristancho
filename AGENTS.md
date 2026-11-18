# GestioRubyCristancho — Guía para agentes

Salón de belleza "Ruby Cristancho": **Laravel 13 API-only** (sin Blade) + **Angular 22** en `frontend/`. La API JSON vive en `routes/api.php` (Sanctum SPA); la web solo expone `/` con metadatos y `/docs` con Swagger UI. UI en español (`APP_LOCALE=es`): ver `frontend/README.md`.

## Comandos

```powershell
php artisan serve                              # API en http://127.0.0.1:8000
cd frontend; npm run dev                       # SPA Angular (Vite) en http://127.0.0.1:4200
cd frontend; npm run build                     # build de producción Angular
composer run setup                             # PHP + .env + migrate + npm install/build de frontend
composer run dev                               # solo `php artisan serve` (API)
composer run test                              # config:clear + artisan test
./vendor/bin/pint --test                       # formateo Pint (sin CI, sin lint en CI)
php artisan test --filter=InventarioTest       # un solo test
```

- La UI se levanta aparte con `cd frontend; npm run dev`; ya no hay Vite raíz ni assets Blade. Si necesitas la cola, usa `php artisan queue:listen` en otra terminal.
- Documentación interactiva: `http://127.0.0.1:8000/docs` (Swagger UI) y `http://127.0.0.1:8000/docs/openapi.json` (OpenAPI generado desde las rutas `api/*`).

## Base de datos

- Real: `.env` → MySQL `beautysalon` (`127.0.0.1:3306`, root/root). `.env.example` dice `sqlite`: **no lo uses**, manda `.env` real.
- Tests: `phpunit.xml` fija `DB_DATABASE=beautysalon_testing`, y los tests usan `DatabaseTransactions` (se revierten; nunca tocan datos reales). `TestCase` no tiene `RefreshDatabase`.
- Para refrescar la base de pruebas desde la real (solo estructura):

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root --no-data --routines --skip-comments beautysalon --result-file="$env:TEMP\schema.sql"
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS beautysalon_testing; CREATE DATABASE beautysalon_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"C:\xampp\mysql\bin\mysql.exe`" -u root beautysalon_testing < $env:TEMP\schema.sql"
```

- `0001_01_01_000003_create_base_business_tables.php` crea las tablas del salón con guardas `hasTable/hasColumn` (idempotente, para instalaciones nuevas) y su `down()` es no-op. Las migraciones posteriores añaden columnas con las mismas guardas; no intentes `migrate:fresh` contra la base real.
- Seeds idempotentes: `ConfiguracionSeeder` + `PermisoSeeder`, y `DatabaseSeeder` crea el admin `admin@rubycristancho.local / Ruby2026*` (si ya existe, solo refresca la contraseña).

## Arquitectura

- **Sin Blade**: no hay vistas Blade (`resources/views` solo conserva un `.gitkeep` para que `view:cache` no falle) ni controladores web de negocio. Si necesitas una pantalla, va en Angular (`frontend/`), no en Blade.
- Rutas API en `routes/api.php`: todo bajo `auth:sanctum`; cada ruta exige `permiso:<clave>` (ej. `permiso:ventas.operar`) y la administración exige `role:admin`. `Estilistas` no lleva `permiso` (solo auth).
- Controllers delgados en `app/Http/Controllers/Api/`, lógica de negocio en `app/Services/` (`InventarioService`, `ConsumoService`, `VentaService`, `CajaService`, …). Los tests llaman a los services directamente con `app(XService::class)` o a la API con `Sanctum::actingAs`.
- Dinero y cantidades **solo** con `App\Support\Money` (BCMath, nunca `float`): escala dinero = 2, cantidad = 3, costo unitario = 6. Las comparaciones usan `Money::mayorQue/menorQue/igual`; el stock se aserta como cadena (`'480.000'`).
- Modelos con `SoftDeletes` mayoritarios; nombres de campos en español. `Producto::UNIDAD_*` / `USO_*` son constantes de clase (ver `producto()` en tests para el shape mínimo).
- No hay `opencode.json`, CI ni pre-commit en el repo.

## Autenticación

- Angular usa Sanctum SPA: `GET /sanctum/csrf-cookie`, `POST /api/login`, `GET /api/me`, `POST /api/logout`. No existe `/login` web.
- Los tests autentican con `Sanctum::actingAs($user)` (no `actingAs()` a secas para rutas `auth:sanctum`).
- Swagger UI incluye un formulario de login propio para probar endpoints con la cookie de sesión.

## Permisos y roles

- `User::puede($clave)`: `admin` pasa todo; el resto se resuelve contra `permiso_roles` (negados ganan). Middleware `EnsurePermission` (`permiso`) aborta 403; `EnsureRole` (`role`) deja pasar a admin siempre.
- Para probar una ruta como admin: crear `User` con `role => User::ROLE_ADMIN` y `Sanctum::actingAs()` (ver `setUp()` de los `Feature/*Test`).

## Inventario (lo más enrevesado)

- Envases **empezados** (`estado_envase=empezado`, `medido=false`) se registran pero **no suman stock**; solo `medirEnvase()` (ruta API `POST /api/productos/{producto}/medir`) suma lo medido vía `InventarioService`.
- `InventarioService::registrarEntrada($producto, $cantidad, $costoTotal, $extra)` y `consumir()` son la única vía autorizada de mover stock; `consumir` permite stock negativo (registro diario no bloquea).
- Ajustes, reactivación y consumo directo del detail de producto: `POST /api/productos/{producto}/{ajustar|reactivar|consumo}`. La SPA mantiene la ficha de producto en modo lectura; esas acciones se hacen desde Swagger/API.
- Carga inicial idempotente: `php artisan inventario:importar [--dry-run|--sin-stock|--pendientes]` (lee `database/data/inventario_inicial.php`, marca lotes con `[import:catalogo-inicial]`; repetirla no duplica). No confundir con `inventario:importar-datos` (solo catálogo/costos ALTER EGO).
- Suite de tests: `tests/Feature/{Inventario,Consumo,Atencion,CierreSemanal}Test.php` (+ `ApiTest`/`ExampleTest`). En `setUp` usan `uniqid()` en nombres para no colisionar dentro de la transacción.
