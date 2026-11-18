<?php

use App\Http\Controllers\Api\AtencionController;
use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CajaController;
use App\Http\Controllers\Api\CategoriaProductoController;
use App\Http\Controllers\Api\CategoriaServicioController;
use App\Http\Controllers\Api\CierreDiarioController;
use App\Http\Controllers\Api\CierreSemanalController;
use App\Http\Controllers\Api\CitaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\ConsumoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EstilistaController;
use App\Http\Controllers\Api\GastoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ProductoVendidoController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ServicioController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/dashboard', DashboardController::class)->middleware(['auth:sanctum', 'permiso:dashboard.ver']);

Route::middleware(['auth:sanctum', 'permiso:inventario.ver'])->group(function () {
    Route::get('/productos', [ProductoController::class, 'index'])->name('api.productos.index');

    // Rutas literales antes de /productos/{producto} para que no las capture
    // el route model binding.
    Route::get('/productos/exportar', [ProductoController::class, 'exportar'])->name('api.productos.exportar');
    Route::get('/productos/revision-precios', [ProductoController::class, 'revisionPrecios'])->name('api.productos.revision-precios');

    Route::get('/productos/{producto}', [ProductoController::class, 'show'])->middleware('audita.acceso:inventario')->name('api.productos.show');
});

Route::middleware(['auth:sanctum', 'permiso:inventario.operar'])->group(function () {
    Route::post('/productos', [ProductoController::class, 'store'])->name('api.productos.store');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])->name('api.productos.update');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->name('api.productos.destroy');
    Route::post('/productos/{producto}/reactivar', [ProductoController::class, 'reactivar'])->name('api.productos.reactivar');
    Route::post('/productos/{producto}/ajustar', [ProductoController::class, 'ajustar'])->name('api.productos.ajustar');
    Route::post('/productos/{producto}/medir', [ProductoController::class, 'medirEnvase'])->name('api.productos.medir');
    Route::post('/productos/{producto}/consumo', [ProductoController::class, 'registrarConsumo'])->name('api.productos.consumo');
    Route::post('/entradas', [ProductoController::class, 'entrada'])->name('api.entradas.store');
});

Route::middleware(['auth:sanctum', 'permiso:ventas.ver'])->group(function () {
    Route::get('/ventas', [VentaController::class, 'index'])->name('api.ventas.index');
    Route::get('/ventas/opciones', [VentaController::class, 'opciones'])->name('api.ventas.opciones');
    Route::get('/ventas/{venta}', [VentaController::class, 'show'])->middleware('audita.acceso:ventas')->name('api.ventas.show');
});

Route::middleware(['auth:sanctum', 'permiso:ventas.operar'])->group(function () {
    Route::post('/ventas', [VentaController::class, 'store'])->name('api.ventas.store');
    Route::post('/ventas/{venta}/pagos', [VentaController::class, 'registrarPago'])->name('api.ventas.pagos');
    Route::post('/ventas/{venta}/anular', [VentaController::class, 'anular'])->name('api.ventas.anular');
});

// Productos vendidos: venta directa de unidades/potes, separada del consumo
// de productos usados en servicios. Reutiliza ventas + inventario.
Route::middleware(['auth:sanctum', 'permiso:ventas.ver'])->group(function () {
    Route::get('/productos-vendidos', [ProductoVendidoController::class, 'index'])->name('api.productos-vendidos.index');
    Route::get('/productos-vendidos/opciones', [ProductoVendidoController::class, 'opciones'])->name('api.productos-vendidos.opciones');
});

Route::middleware(['auth:sanctum', 'permiso:ventas.operar'])->group(function () {
    Route::post('/productos-vendidos', [ProductoVendidoController::class, 'store'])->name('api.productos-vendidos.store');
});

Route::middleware(['auth:sanctum', 'permiso:caja.ver'])->group(function () {
    Route::get('/caja', [CajaController::class, 'index'])->name('api.caja.index');
    Route::get('/caja/{caja}', [CajaController::class, 'show'])->middleware('audita.acceso:caja')->name('api.caja.show');
});

Route::middleware(['auth:sanctum', 'permiso:caja.operar'])->group(function () {
    Route::post('/caja', [CajaController::class, 'store'])->name('api.caja.store');
    Route::post('/caja/movimiento', [CajaController::class, 'movimiento'])->name('api.caja.movimiento');
    Route::post('/caja/{caja}/cerrar', [CajaController::class, 'cerrar'])->name('api.caja.cerrar');
    Route::post('/caja/{caja}/reabrir', [CajaController::class, 'reabrir'])->name('api.caja.reabrir');
});

Route::middleware(['auth:sanctum', 'permiso:clientes.ver'])->group(function () {
    Route::get('/clientes', [ClienteController::class, 'index'])->name('api.clientes.index');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->middleware('audita.acceso:clientes')->name('api.clientes.show');
});

Route::middleware(['auth:sanctum', 'permiso:clientes.operar'])->group(function () {
    Route::post('/clientes', [ClienteController::class, 'store'])->name('api.clientes.store');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('api.clientes.update');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('api.clientes.destroy');
    Route::post('/clientes/{cliente}/reactivar', [ClienteController::class, 'reactivar'])->name('api.clientes.reactivar');
});

Route::middleware(['auth:sanctum', 'permiso:servicios.ver'])->group(function () {
    Route::get('/servicios', [ServicioController::class, 'index'])->name('api.servicios.index');
    Route::get('/servicios/{servicio}', [ServicioController::class, 'show'])->middleware('audita.acceso:servicios')->name('api.servicios.show');
});

Route::middleware(['auth:sanctum', 'permiso:servicios.operar'])->group(function () {
    Route::post('/servicios', [ServicioController::class, 'store'])->name('api.servicios.store');
    Route::put('/servicios/{servicio}', [ServicioController::class, 'update'])->name('api.servicios.update');
    Route::delete('/servicios/{servicio}', [ServicioController::class, 'destroy'])->name('api.servicios.destroy');
    Route::post('/servicios/{servicio}/receta', [ServicioController::class, 'registrarReceta'])->name('api.servicios.receta');
});

// Citas: permisos citas.ver / citas.operar, reutilizando CitaService.
Route::middleware(['auth:sanctum', 'permiso:citas.ver'])->group(function () {
    Route::get('/citas', [CitaController::class, 'index'])->name('api.citas.index');
    Route::get('/citas/{cita}', [CitaController::class, 'show'])->middleware('audita.acceso:citas')->name('api.citas.show');
});

Route::middleware(['auth:sanctum', 'permiso:citas.operar'])->group(function () {
    Route::post('/citas', [CitaController::class, 'store'])->name('api.citas.store');
    Route::put('/citas/{cita}', [CitaController::class, 'update'])->name('api.citas.update');
    Route::delete('/citas/{cita}', [CitaController::class, 'destroy'])->name('api.citas.destroy');
    Route::patch('/citas/{cita}/estado', [CitaController::class, 'cambiarEstado'])->name('api.citas.estado');
});

// Profesionales: en web solo exigen auth (sin permiso), se replica aquí.
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/estilistas', [EstilistaController::class, 'index'])->name('api.estilistas.index');
    Route::post('/estilistas', [EstilistaController::class, 'store'])->name('api.estilistas.store');
    Route::get('/estilistas/{estilista}', [EstilistaController::class, 'show'])->middleware('audita.acceso:estilistas')->name('api.estilistas.show');
    Route::put('/estilistas/{estilista}', [EstilistaController::class, 'update'])->name('api.estilistas.update');
    Route::delete('/estilistas/{estilista}', [EstilistaController::class, 'destroy'])->name('api.estilistas.destroy');
    Route::post('/estilistas/{estilista}/agenda', [EstilistaController::class, 'guardarAgenda'])->name('api.estilistas.agenda');
});

Route::middleware(['auth:sanctum', 'permiso:proveedores.ver'])->group(function () {
    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('api.proveedores.index');
    Route::get('/proveedores/{proveedor}', [ProveedorController::class, 'show'])->middleware('audita.acceso:proveedores')->name('api.proveedores.show');
});

Route::middleware(['auth:sanctum', 'permiso:proveedores.operar'])->group(function () {
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('api.proveedores.store');
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('api.proveedores.update');
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('api.proveedores.destroy');
});

Route::middleware(['auth:sanctum', 'permiso:gastos.ver'])->group(function () {
    Route::get('/gastos', [GastoController::class, 'index'])->name('api.gastos.index');
    Route::get('/gastos/{gasto}', [GastoController::class, 'show'])->middleware('audita.acceso:gastos')->name('api.gastos.show');
});

Route::middleware(['auth:sanctum', 'permiso:gastos.operar'])->group(function () {
    Route::post('/gastos', [GastoController::class, 'store'])->name('api.gastos.store');
    Route::put('/gastos/{gasto}', [GastoController::class, 'update'])->name('api.gastos.update');
    Route::delete('/gastos/{gasto}', [GastoController::class, 'destroy'])->name('api.gastos.destroy');
});

Route::middleware(['auth:sanctum', 'permiso:inventario.ver'])->group(function () {
    Route::get('/categoria-productos', [CategoriaProductoController::class, 'index'])->name('api.categoria-productos.index');
});

Route::middleware(['auth:sanctum', 'permiso:inventario.operar'])->group(function () {
    Route::post('/categoria-productos', [CategoriaProductoController::class, 'store'])->name('api.categoria-productos.store');
    Route::put('/categoria-productos/{categoriaProducto}', [CategoriaProductoController::class, 'update'])->name('api.categoria-productos.update');
    Route::delete('/categoria-productos/{categoriaProducto}', [CategoriaProductoController::class, 'destroy'])->name('api.categoria-productos.destroy');
});

Route::middleware(['auth:sanctum', 'permiso:servicios.ver'])->group(function () {
    Route::get('/categoria-servicios', [CategoriaServicioController::class, 'index'])->name('api.categoria-servicios.index');
});

Route::middleware(['auth:sanctum', 'permiso:servicios.operar'])->group(function () {
    Route::post('/categoria-servicios', [CategoriaServicioController::class, 'store'])->name('api.categoria-servicios.store');
    Route::put('/categoria-servicios/{categoriaServicio}', [CategoriaServicioController::class, 'update'])->name('api.categoria-servicios.update');
    Route::delete('/categoria-servicios/{categoriaServicio}', [CategoriaServicioController::class, 'destroy'])->name('api.categoria-servicios.destroy');
});

// Reportes: permiso reportes.ver, solo GET.
Route::middleware(['auth:sanctum', 'permiso:reportes.ver'])->group(function () {
    Route::get('/reportes', [ReporteController::class, 'index'])->name('api.reportes.index');
    Route::get('/reportes/resultados', [ReporteController::class, 'resultados'])->name('api.reportes.resultados');
    Route::get('/reportes/inventario', [ReporteController::class, 'inventario'])->name('api.reportes.inventario');
    Route::get('/reportes/consumo', [ReporteController::class, 'consumo'])->name('api.reportes.consumo');
    Route::get('/reportes/cartera', [ReporteController::class, 'cartera'])->name('api.reportes.cartera');

    Route::get('/reportes/cierres', [CierreSemanalController::class, 'index'])->name('api.cierres.index');
    Route::get('/reportes/cierres/{cierre}', [CierreSemanalController::class, 'show'])->middleware('audita.acceso:cierres')->name('api.cierres.show');

    // Cierre diario e histórico: consulta para cualquier usuario con reportes.
    Route::get('/cierres-diarios', [CierreDiarioController::class, 'index'])->name('api.cierres-diarios.index');
    Route::get('/cierres-diarios/historial', [CierreDiarioController::class, 'historial'])->name('api.cierres-diarios.historial');
    Route::get('/cierres-diarios/{cierre}', [CierreDiarioController::class, 'show'])->middleware('audita.acceso:cierres_diarios')->name('api.cierres-diarios.show');
});

// Cerrar o reabrir un día es exclusivo del administrador.
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/reportes/cierres', [CierreSemanalController::class, 'store'])->name('api.cierres.store');
    Route::delete('/reportes/cierres/{cierre}', [CierreSemanalController::class, 'destroy'])->name('api.cierres.destroy');

    Route::post('/cierres-diarios', [CierreDiarioController::class, 'store'])->name('api.cierres-diarios.store');
    Route::post('/cierres-diarios/{cierre}/reabrir', [CierreDiarioController::class, 'reabrir'])->name('api.cierres-diarios.reabrir');
});

// Administración: solo role:admin.
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('api.usuarios.index');
    Route::get('/usuarios/permisos', [UsuarioController::class, 'permisos'])->name('api.usuarios.permisos');
    Route::post('/usuarios/permisos', [UsuarioController::class, 'guardarPermisos'])->name('api.usuarios.permisos.guardar');
    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware('audita.acceso:usuarios')->name('api.usuarios.show');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('api.usuarios.store');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('api.usuarios.update');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('api.usuarios.destroy');

    Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('api.auditoria.index');

    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('api.configuracion.index');
    Route::post('/configuracion', [ConfiguracionController::class, 'guardar'])->name('api.configuracion.guardar');
    Route::post('/configuracion/metodos', [ConfiguracionController::class, 'guardarMetodo'])->name('api.configuracion.metodo');
    Route::post('/configuracion/categorias', [ConfiguracionController::class, 'guardarCategoria'])->name('api.configuracion.categoria');
});

// Compras: permisos compras.ver / compras.operar, reutilizando CompraService.
Route::middleware(['auth:sanctum', 'permiso:compras.ver'])->group(function () {
    Route::get('/compras', [CompraController::class, 'index'])->name('api.compras.index');
    Route::get('/compras/opciones', [CompraController::class, 'opciones'])->name('api.compras.opciones');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])->middleware('audita.acceso:compras')->name('api.compras.show');
});

Route::middleware(['auth:sanctum', 'permiso:compras.operar'])->group(function () {
    Route::post('/compras', [CompraController::class, 'store'])->name('api.compras.store');
    Route::post('/compras/{compra}/pagos', [CompraController::class, 'registrarPago'])->name('api.compras.pagos');
});

// Consumos / registro diario de productos: permisos inventario.ver/operar.
Route::middleware(['auth:sanctum', 'permiso:inventario.ver'])->group(function () {
    Route::get('/consumos', [ConsumoController::class, 'index'])->name('api.consumos.index');
    Route::get('/consumos/opciones', [ConsumoController::class, 'opciones'])->name('api.consumos.opciones');
    Route::get('/consumos/{consumo}', [ConsumoController::class, 'show'])->middleware('audita.acceso:consumos')->name('api.consumos.show');
});

Route::middleware(['auth:sanctum', 'permiso:inventario.operar'])->group(function () {
    Route::post('/consumos', [ConsumoController::class, 'store'])->name('api.consumos.store');
    Route::post('/consumos/guardar-producto', [ConsumoController::class, 'guardarProducto'])->name('api.consumos.guardarProducto');
    Route::put('/consumos/{consumo}', [ConsumoController::class, 'update'])->name('api.consumos.update');
    Route::post('/consumos/{consumo}/anular', [ConsumoController::class, 'anular'])->name('api.consumos.anular');
    Route::post('/consumos/{consumo}/quitar-producto', [ConsumoController::class, 'quitarProducto'])->name('api.consumos.quitarProducto');
    Route::patch('/consumos/{consumo}/cabecera', [ConsumoController::class, 'actualizarCabecera'])->name('api.consumos.cabecera');
    Route::delete('/consumos/{consumo}', [ConsumoController::class, 'destroy'])->name('api.consumos.destroy');
});

// Atenciones / registro diario de clientes: permisos clientes.ver/operar.
Route::middleware(['auth:sanctum', 'permiso:clientes.ver'])->group(function () {
    Route::get('/atenciones', [AtencionController::class, 'index'])->name('api.atenciones.index');
    Route::get('/atenciones/opciones', [AtencionController::class, 'opciones'])->name('api.atenciones.opciones');
    Route::get('/atenciones/{atencion}', [AtencionController::class, 'show'])->middleware('audita.acceso:atenciones')->name('api.atenciones.show');
});

Route::middleware(['auth:sanctum', 'permiso:clientes.operar'])->group(function () {
    Route::post('/atenciones', [AtencionController::class, 'store'])->name('api.atenciones.store');
    Route::put('/atenciones/{atencion}', [AtencionController::class, 'update'])->name('api.atenciones.update');
    Route::post('/atenciones/{atencion}/anular', [AtencionController::class, 'anular'])->name('api.atenciones.anular');
    Route::delete('/atenciones/{atencion}', [AtencionController::class, 'destroy'])->name('api.atenciones.destroy');
});
