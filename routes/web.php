<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaProductoController;
use App\Http\Controllers\CategoriaServicioController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConsumoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstilistaController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::redirect('/', '/panel');

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('panel', DashboardController::class)->name('dashboard')->middleware('permiso:dashboard.ver');

    /*
    |----------------------------------------------------------------------
    | Caja diaria
    |----------------------------------------------------------------------
    */
    Route::get('caja', [CajaController::class, 'index'])->name('caja.index')->middleware('permiso:caja.ver');
    Route::get('caja/crear', [CajaController::class, 'create'])->name('caja.create')->middleware('permiso:caja.operar');
    Route::post('caja', [CajaController::class, 'store'])->name('caja.store')->middleware('permiso:caja.operar');
    Route::post('caja/movimiento', [CajaController::class, 'movimiento'])->name('caja.movimiento')->middleware('permiso:caja.operar');
    Route::get('caja/{caja}', [CajaController::class, 'show'])->name('caja.show')->middleware('permiso:caja.ver');
    Route::post('caja/{caja}/cerrar', [CajaController::class, 'cerrar'])->name('caja.cerrar')->middleware('permiso:caja.operar');
    Route::post('caja/{caja}/reabrir', [CajaController::class, 'reabrir'])->name('caja.reabrir')->middleware('permiso:caja.operar');

    /*
    |----------------------------------------------------------------------
    | Ventas
    |----------------------------------------------------------------------
    */
    Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index')->middleware('permiso:ventas.ver');
    Route::get('ventas/crear', [VentaController::class, 'create'])->name('ventas.create')->middleware('permiso:ventas.operar');
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store')->middleware('permiso:ventas.operar');
    Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show')->middleware('permiso:ventas.ver');
    Route::get('ventas/{venta}/recibo', [VentaController::class, 'recibo'])->name('ventas.recibo')->middleware('permiso:ventas.ver');
    Route::post('ventas/{venta}/pagos', [VentaController::class, 'registrarPago'])->name('ventas.pagos')->middleware('permiso:ventas.operar');
    Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular')->middleware('permiso:ventas.operar');

    /*
    |----------------------------------------------------------------------
    | Inventario
    |----------------------------------------------------------------------
    */
    Route::get('inventario', [ProductoController::class, 'index'])->name('productos.index')->middleware('permiso:inventario.ver');
    Route::get('inventario/crear', [ProductoController::class, 'create'])->name('productos.create')->middleware('permiso:inventario.operar');
    Route::post('inventario', [ProductoController::class, 'store'])->name('productos.store')->middleware('permiso:inventario.operar');
    Route::get('inventario/entrada', [ProductoController::class, 'entradaForm'])->name('productos.entrada')->middleware('permiso:inventario.operar');
    Route::post('inventario/entrada', [ProductoController::class, 'registrarEntrada'])->name('productos.entrada.store')->middleware('permiso:inventario.operar');
    Route::get('inventario/exportar', [ProductoController::class, 'exportar'])->name('productos.exportar')->middleware('permiso:inventario.ver');
    Route::get('inventario/revision-precios', [ProductoController::class, 'revisarPrecios'])->name('productos.revision-precios')->middleware('permiso:inventario.ver');
    Route::post('inventario/{producto}/medir', [ProductoController::class, 'medirEnvase'])->name('productos.medir')->middleware('permiso:inventario.operar');
    Route::get('inventario/{producto}', [ProductoController::class, 'show'])->name('productos.show')->middleware('permiso:inventario.ver');
    Route::get('inventario/{producto}/editar', [ProductoController::class, 'edit'])->name('productos.edit')->middleware('permiso:inventario.operar');
    Route::put('inventario/{producto}', [ProductoController::class, 'update'])->name('productos.update')->middleware('permiso:inventario.operar');
    Route::delete('inventario/{producto}', [ProductoController::class, 'destroy'])->name('productos.destroy')->middleware('permiso:inventario.operar');
    Route::post('inventario/{producto}/reactivar', [ProductoController::class, 'reactivar'])->name('productos.reactivar')->middleware('permiso:inventario.operar');
    Route::post('inventario/{producto}/ajustar', [ProductoController::class, 'ajustar'])->name('productos.ajustar')->middleware('permiso:inventario.operar');
    Route::post('inventario/{producto}/consumo', [ProductoController::class, 'registrarConsumo'])->name('productos.consumo')->middleware('permiso:inventario.operar');

    /*
    |----------------------------------------------------------------------
    | Consumos / registro diario
    |----------------------------------------------------------------------
    */
    Route::get('consumos', [ConsumoController::class, 'index'])->name('consumos.index')->middleware('permiso:inventario.ver');
    Route::get('consumos/registrar', [ConsumoController::class, 'create'])->name('consumos.create')->middleware('permiso:inventario.operar');
    Route::post('consumos', [ConsumoController::class, 'store'])->name('consumos.store')->middleware('permiso:inventario.operar');
    Route::post('consumos/guardar-producto', [ConsumoController::class, 'guardarProducto'])->name('consumos.guardarProducto')->middleware('permiso:inventario.operar');
    Route::post('consumos/{consumo}/quitar-producto', [ConsumoController::class, 'quitarProducto'])->name('consumos.quitarProducto')->middleware('permiso:inventario.operar');
    Route::patch('consumos/{consumo}/cabecera', [ConsumoController::class, 'actualizarCabecera'])->name('consumos.cabecera')->middleware('permiso:inventario.operar');
    Route::get('consumos/{consumo}', [ConsumoController::class, 'show'])->name('consumos.show')->middleware('permiso:inventario.ver');
    Route::get('consumos/{consumo}/editar', [ConsumoController::class, 'edit'])->name('consumos.edit')->middleware('permiso:inventario.operar');
    Route::post('consumos/{consumo}/anular', [ConsumoController::class, 'anular'])->name('consumos.anular')->middleware('permiso:inventario.operar');
    Route::put('consumos/{consumo}', [ConsumoController::class, 'update'])->name('consumos.update')->middleware('permiso:inventario.operar');
    Route::delete('consumos/{consumo}', [ConsumoController::class, 'destroy'])->name('consumos.destroy')->middleware('permiso:inventario.operar');

    /*
    |----------------------------------------------------------------------
    | Registro diario de clientes (atenciones)
    |----------------------------------------------------------------------
    */
    Route::get('atenciones', [AtencionController::class, 'index'])->name('atenciones.index')->middleware('permiso:clientes.ver');
    Route::get('atenciones/crear', [AtencionController::class, 'create'])->name('atenciones.create')->middleware('permiso:clientes.operar');
    Route::post('atenciones', [AtencionController::class, 'store'])->name('atenciones.store')->middleware('permiso:clientes.operar');
    Route::get('atenciones/{atencion}', [AtencionController::class, 'show'])->name('atenciones.show')->middleware('permiso:clientes.ver');
    Route::get('atenciones/{atencion}/editar', [AtencionController::class, 'edit'])->name('atenciones.edit')->middleware('permiso:clientes.operar');
    Route::put('atenciones/{atencion}', [AtencionController::class, 'update'])->name('atenciones.update')->middleware('permiso:clientes.operar');
    Route::post('atenciones/{atencion}/anular', [AtencionController::class, 'anular'])->name('atenciones.anular')->middleware('permiso:clientes.operar');
    Route::delete('atenciones/{atencion}', [AtencionController::class, 'destroy'])->name('atenciones.destroy')->middleware('permiso:clientes.operar');

    /*
    |----------------------------------------------------------------------
    | Compras
    |----------------------------------------------------------------------
    */
    Route::get('compras', [CompraController::class, 'index'])->name('compras.index')->middleware('permiso:compras.ver');
    Route::get('compras/crear', [CompraController::class, 'create'])->name('compras.create')->middleware('permiso:compras.operar');
    Route::post('compras', [CompraController::class, 'store'])->name('compras.store')->middleware('permiso:compras.operar');
    Route::get('compras/{compra}', [CompraController::class, 'show'])->name('compras.show')->middleware('permiso:compras.ver');
    Route::post('compras/{compra}/pagos', [CompraController::class, 'registrarPago'])->name('compras.pagos')->middleware('permiso:compras.operar');

    /*
    |----------------------------------------------------------------------
    | Clientes
    |----------------------------------------------------------------------
    */
    Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index')->middleware('permiso:clientes.ver');
    Route::get('clientes/crear', [ClienteController::class, 'create'])->name('clientes.create')->middleware('permiso:clientes.operar');
    Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store')->middleware('permiso:clientes.operar');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show')->middleware('permiso:clientes.ver');
    Route::get('clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit')->middleware('permiso:clientes.operar');
    Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update')->middleware('permiso:clientes.operar');
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy')->middleware('permiso:clientes.operar');
    Route::post('clientes/{cliente}/reactivar', [ClienteController::class, 'reactivar'])->name('clientes.reactivar')->middleware('permiso:clientes.operar');

    /*
    |----------------------------------------------------------------------
    | Citas
    |----------------------------------------------------------------------
    */
    Route::get('citas', [CitaController::class, 'index'])->name('citas.index')->middleware('permiso:citas.ver');
    Route::get('citas/crear', [CitaController::class, 'create'])->name('citas.create')->middleware('permiso:citas.operar');
    Route::post('citas', [CitaController::class, 'store'])->name('citas.store')->middleware('permiso:citas.operar');
    Route::get('citas/{cita}', [CitaController::class, 'show'])->name('citas.show')->middleware('permiso:citas.ver');
    Route::get('citas/{cita}/editar', [CitaController::class, 'edit'])->name('citas.edit')->middleware('permiso:citas.operar');
    Route::put('citas/{cita}', [CitaController::class, 'update'])->name('citas.update')->middleware('permiso:citas.operar');
    Route::delete('citas/{cita}', [CitaController::class, 'destroy'])->name('citas.destroy')->middleware('permiso:citas.operar');
    Route::patch('citas/{cita}/estado', [CitaController::class, 'cambiarEstado'])->name('citas.estado')->middleware('permiso:citas.operar');

    /*
    |----------------------------------------------------------------------
    | Servicios
    |----------------------------------------------------------------------
    */
    Route::get('servicios', [ServicioController::class, 'index'])->name('servicios.index')->middleware('permiso:servicios.ver');
    Route::get('servicios/crear', [ServicioController::class, 'create'])->name('servicios.create')->middleware('permiso:servicios.operar');
    Route::post('servicios', [ServicioController::class, 'store'])->name('servicios.store')->middleware('permiso:servicios.operar');
    Route::get('servicios/{servicio}', [ServicioController::class, 'show'])->name('servicios.show')->middleware('permiso:servicios.ver');
    Route::get('servicios/{servicio}/editar', [ServicioController::class, 'edit'])->name('servicios.edit')->middleware('permiso:servicios.operar');
    Route::put('servicios/{servicio}', [ServicioController::class, 'update'])->name('servicios.update')->middleware('permiso:servicios.operar');
    Route::delete('servicios/{servicio}', [ServicioController::class, 'destroy'])->name('servicios.destroy')->middleware('permiso:servicios.operar');
    Route::post('servicios/{servicio}/receta', [ServicioController::class, 'guardarReceta'])->name('servicios.receta')->middleware('permiso:servicios.operar');

    /*
    |----------------------------------------------------------------------
    | Profesionales
    |----------------------------------------------------------------------
    */
    Route::get('estilistas', [EstilistaController::class, 'index'])->name('estilistas.index');
    Route::get('estilistas/crear', [EstilistaController::class, 'create'])->name('estilistas.create');
    Route::post('estilistas', [EstilistaController::class, 'store'])->name('estilistas.store');
    Route::get('estilistas/{estilista}', [EstilistaController::class, 'show'])->name('estilistas.show');
    Route::get('estilistas/{estilista}/editar', [EstilistaController::class, 'edit'])->name('estilistas.edit');
    Route::put('estilistas/{estilista}', [EstilistaController::class, 'update'])->name('estilistas.update');
    Route::delete('estilistas/{estilista}', [EstilistaController::class, 'destroy'])->name('estilistas.destroy');
    Route::post('estilistas/{estilista}/agenda', [EstilistaController::class, 'guardarAgenda'])->name('estilistas.agenda');

    /*
    |----------------------------------------------------------------------
    | Proveedores
    |----------------------------------------------------------------------
    */
    Route::get('proveedores', [ProveedorController::class, 'index'])->name('proveedores.index')->middleware('permiso:proveedores.ver');
    Route::get('proveedores/crear', [ProveedorController::class, 'create'])->name('proveedores.create')->middleware('permiso:proveedores.operar');
    Route::post('proveedores', [ProveedorController::class, 'store'])->name('proveedores.store')->middleware('permiso:proveedores.operar');
    Route::get('proveedores/{proveedor}', [ProveedorController::class, 'show'])->name('proveedores.show')->middleware('permiso:proveedores.ver');
    Route::get('proveedores/{proveedor}/editar', [ProveedorController::class, 'edit'])->name('proveedores.edit')->middleware('permiso:proveedores.operar');
    Route::put('proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update')->middleware('permiso:proveedores.operar');
    Route::delete('proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('proveedores.destroy')->middleware('permiso:proveedores.operar');

    /*
    |----------------------------------------------------------------------
    | Categorías
    |----------------------------------------------------------------------
    */
    Route::get('categoria-productos', [CategoriaProductoController::class, 'index'])->name('categoria-productos.index')->middleware('permiso:inventario.ver');
    Route::post('categoria-productos', [CategoriaProductoController::class, 'store'])->name('categoria-productos.store')->middleware('permiso:inventario.operar');
    Route::put('categoria-productos/{categoriaProducto}', [CategoriaProductoController::class, 'update'])->name('categoria-productos.update')->middleware('permiso:inventario.operar');
    Route::delete('categoria-productos/{categoriaProducto}', [CategoriaProductoController::class, 'destroy'])->name('categoria-productos.destroy')->middleware('permiso:inventario.operar');

    Route::get('categoria-servicios', [CategoriaServicioController::class, 'index'])->name('categoria-servicios.index')->middleware('permiso:servicios.ver');
    Route::post('categoria-servicios', [CategoriaServicioController::class, 'store'])->name('categoria-servicios.store')->middleware('permiso:servicios.operar');
    Route::put('categoria-servicios/{categoriaServicio}', [CategoriaServicioController::class, 'update'])->name('categoria-servicios.update')->middleware('permiso:servicios.operar');
    Route::delete('categoria-servicios/{categoriaServicio}', [CategoriaServicioController::class, 'destroy'])->name('categoria-servicios.destroy')->middleware('permiso:servicios.operar');

    /*
    |----------------------------------------------------------------------
    | Gastos
    |----------------------------------------------------------------------
    */
    Route::get('gastos', [GastoController::class, 'index'])->name('gastos.index')->middleware('permiso:gastos.ver');
    Route::get('gastos/crear', [GastoController::class, 'create'])->name('gastos.create')->middleware('permiso:gastos.operar');
    Route::post('gastos', [GastoController::class, 'store'])->name('gastos.store')->middleware('permiso:gastos.operar');
    Route::get('gastos/{gasto}', [GastoController::class, 'show'])->name('gastos.show')->middleware('permiso:gastos.ver');
    Route::get('gastos/{gasto}/editar', [GastoController::class, 'edit'])->name('gastos.edit')->middleware('permiso:gastos.operar');
    Route::put('gastos/{gasto}', [GastoController::class, 'update'])->name('gastos.update')->middleware('permiso:gastos.operar');
    Route::delete('gastos/{gasto}', [GastoController::class, 'destroy'])->name('gastos.destroy')->middleware('permiso:gastos.operar');

    /*
    |----------------------------------------------------------------------
    | Reportes
    |----------------------------------------------------------------------
    */
    Route::middleware('permiso:reportes.ver')->group(function () {
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('reportes/resultados', [ReporteController::class, 'resultados'])->name('reportes.resultados');
        Route::get('reportes/inventario', [ReporteController::class, 'inventario'])->name('reportes.inventario');
        Route::get('reportes/consumo', [ReporteController::class, 'consumo'])->name('reportes.consumo');
        Route::get('reportes/cartera', [ReporteController::class, 'cartera'])->name('reportes.cartera');
    });

    /*
    |----------------------------------------------------------------------
    | Administración
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/permisos', [UsuarioController::class, 'permisos'])->name('usuarios.permisos');
        Route::post('usuarios/permisos', [UsuarioController::class, 'guardarPermisos'])->name('usuarios.permisos.guardar');
        Route::get('usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

        Route::get('configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
        Route::post('configuracion', [ConfiguracionController::class, 'guardar'])->name('configuracion.guardar');
        Route::post('configuracion/metodos', [ConfiguracionController::class, 'guardarMetodo'])->name('configuracion.metodo');
        Route::post('configuracion/categorias', [ConfiguracionController::class, 'guardarCategoria'])->name('configuracion.categoria');
    });
});
