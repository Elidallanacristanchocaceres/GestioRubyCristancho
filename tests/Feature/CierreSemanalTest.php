<?php

namespace Tests\Feature;

use App\Models\CategoriaGasto;
use App\Models\CategoriaProducto;
use App\Models\CierreSemanal;
use App\Models\Consumo;
use App\Models\ConsumoServicio;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\CierreSemanalService;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cierre semanal: ingresos, costos, gastos, base, diezmo y neto.
 *
 * Semana de prueba: lunes 9 a domingo 15 de noviembre de 2026.
 */
class CierreSemanalTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Cierre',
            'cedula' => '1111111111',
            'email' => 'cierre@test.local',
            'password' => 'secreto123',
            'role' => User::ROLE_ADMIN,
        ]);

        Sanctum::actingAs($this->admin);
    }

    private function consumo(string $fecha, string $cobrado, string $costo): Consumo
    {
        return Consumo::create([
            'token' => (string) uniqid('consumo-', true),
            'fecha' => $fecha,
            'precio_cobrado' => $cobrado,
            'costo_total' => $costo,
            'estado' => Consumo::ESTADO_ACTIVO,
            'created_by' => $this->admin->id,
        ]);
    }

    private function ventaProducto(string $fecha, string $subtotal, string $costo): void
    {
        $venta = Venta::create([
            'numero' => 'T-'.uniqid(),
            'fecha' => $fecha,
            'fecha_hora' => $fecha.' 10:00:00',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'total_pagado' => $subtotal,
            'saldo_pendiente' => '0',
            'estado' => 'pagada',
            'tipo' => 'producto',
            'created_by' => $this->admin->id,
        ]);

        $venta->detalles()->create([
            'tipo' => 'producto',
            'descripcion' => 'Pote de prueba',
            'cantidad' => '1',
            'unidad' => 'unidad',
            'precio_unitario' => $subtotal,
            'descuento' => '0',
            'subtotal' => $subtotal,
            'costo_unitario' => $costo,
            'costo_total' => $costo,
        ]);
    }

    private function ventaServicio(string $fecha, string $subtotal): Venta
    {
        $venta = Venta::create([
            'numero' => 'S-'.uniqid(),
            'fecha' => $fecha,
            'fecha_hora' => $fecha.' 11:00:00',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'total_pagado' => $subtotal,
            'saldo_pendiente' => '0',
            'estado' => 'pagada',
            'tipo' => 'servicio',
            'created_by' => $this->admin->id,
        ]);

        $venta->detalles()->create([
            'tipo' => 'servicio',
            'descripcion' => 'Servicio de prueba',
            'cantidad' => '1',
            'unidad' => 'unidad',
            'precio_unitario' => $subtotal,
            'descuento' => '0',
            'subtotal' => $subtotal,
            'costo_unitario' => '0',
            'costo_total' => '0',
        ]);

        return $venta;
    }

    private function producto(string $costoUnitario): Producto
    {
        $categoria = CategoriaProducto::firstOrCreate(['nombre' => 'Pruebas cierre']);

        return Producto::create([
            'categoria_producto_id' => $categoria->id,
            'nombre' => 'Producto cierre '.uniqid(),
            'unidad_control' => Producto::UNIDAD_GRAMO,
            'contenido_envase' => '1000',
            'costo_envase' => '100000',
            'costo_unitario' => $costoUnitario,
            'stock' => '0',
            'stock_minimo' => '0',
            'activo' => true,
        ]);
    }

    public function test_calcula_la_semana_y_el_diezmo(): void
    {
        // Servicios cobrados: 100.000 + 50.000
        $this->consumo('2026-11-09', '100000', '30000');
        $this->consumo('2026-11-10', '50000', '10000');

        // Venta de producto: 40.000 con costo 15.000
        $this->ventaProducto('2026-11-10', '40000', '15000');

        // Gasto pagado dentro de la semana.
        $categoria = CategoriaGasto::firstOrCreate(['nombre' => 'Pruebas cierre'], ['activo' => true]);
        Gasto::create([
            'monto' => '20000',
            'categoria_gasto_id' => $categoria->id,
            'estado' => 'pagado',
            'descripcion' => 'Gasto de prueba',
            'fecha' => '2026-11-11',
            'created_by' => $this->admin->id,
        ]);

        $cierre = app(CierreSemanalService::class)->cerrar([
            'semana' => '2026-11-09',
            'notas' => 'Semana de prueba',
        ]);

        $this->assertSame('2026-11-09', $cierre->fecha_inicio->toDateString());
        $this->assertSame('2026-11-15', $cierre->fecha_fin->toDateString());
        $this->assertSame('150000.00', (string) $cierre->ingresos_servicios);
        $this->assertSame('40000.00', (string) $cierre->ingresos_productos);
        $this->assertSame('40000.00', (string) $cierre->costo_productos_consumidos);
        $this->assertSame('15000.00', (string) $cierre->costo_productos_vendidos);
        $this->assertSame('20000.00', (string) $cierre->gastos);
        $this->assertSame('115000.00', (string) $cierre->base_diezmo);
        $this->assertSame('10.00', (string) $cierre->porcentaje_diezmo);
        $this->assertSame('11500.00', (string) $cierre->diezmo);
        $this->assertSame('103500.00', (string) $cierre->neto);
        $this->assertCount(7, $cierre->detalle ?? []);
    }

    public function test_no_permite_cerrar_dos_veces_la_misma_semana(): void
    {
        app(CierreSemanalService::class)->cerrar(['semana' => '2026-11-09']);

        $this->expectException(ValidationException::class);

        app(CierreSemanalService::class)->cerrar(['semana' => '2026-11-09']);
    }

    public function test_si_la_base_es_negativa_el_diezmo_es_cero(): void
    {
        $categoria = CategoriaGasto::firstOrCreate(['nombre' => 'Pruebas cierre'], ['activo' => true]);
        Gasto::create([
            'monto' => '50000',
            'categoria_gasto_id' => $categoria->id,
            'estado' => 'pagado',
            'descripcion' => 'Gasto sin ingresos',
            'fecha' => '2026-11-09',
            'created_by' => $this->admin->id,
        ]);

        $cierre = app(CierreSemanalService::class)->cerrar(['semana' => '2026-11-09']);

        $this->assertSame('-50000.00', (string) $cierre->base_diezmo);
        $this->assertSame('0.00', (string) $cierre->diezmo);
        $this->assertSame('-50000.00', (string) $cierre->neto);
    }

    public function test_admin_puede_listar_el_cierre_semanal_por_api(): void
    {
        $this->getJson('/api/reportes/cierres?semana=2026-11-09')
            ->assertOk()
            ->assertJsonPath('semana.inicio', '2026-11-09')
            ->assertJsonPath('semana.fin', '2026-11-15')
            ->assertJsonStructure(['semana', 'resumen', 'cierre', 'historial', 'semanas']);
    }

    public function test_el_cierre_se_guarda_en_la_base_de_datos(): void
    {
        app(CierreSemanalService::class)->cerrar(['semana' => '2026-11-09']);

        $this->assertDatabaseHas('cierres_semanales', [
            'fecha_inicio' => '2026-11-09',
            'fecha_fin' => '2026-11-15',
            'estado' => CierreSemanal::ESTADO_CERRADO,
        ]);
    }

    public function test_show_del_cierre_por_api(): void
    {
        $cierre = app(CierreSemanalService::class)->cerrar(['semana' => '2026-11-09']);

        $this->getJson('/api/reportes/cierres/'.$cierre->id)
            ->assertOk()
            ->assertJsonPath('cierre.id', $cierre->id)
            ->assertJsonStructure([
                'cierre' => [
                    'ingresos_servicios',
                    'ingresos_productos',
                    'costo_productos_consumidos',
                    'costo_productos_vendidos',
                    'gastos',
                    'base_diezmo',
                    'diezmo',
                    'neto',
                    'detalle',
                ],
            ]);
    }

    public function test_si_no_hay_registro_diario_usa_ventas_de_servicios(): void
    {
        $venta = $this->ventaServicio('2026-11-10', '100000');
        $producto = $this->producto('500');

        ConsumoServicio::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => '10',
            'unidad' => $producto->unidad_control,
            'costo_unitario' => '500',
            'costo_total' => '5000',
            'estado' => 'activo',
            'created_by' => $this->admin->id,
        ]);

        $this->ventaProducto('2026-11-10', '40000', '15000');

        $cierre = app(CierreSemanalService::class)->cerrar(['semana' => '2026-11-09']);

        $this->assertSame('100000.00', (string) $cierre->ingresos_servicios);
        $this->assertSame('40000.00', (string) $cierre->ingresos_productos);
        $this->assertSame('5000.00', (string) $cierre->costo_productos_consumidos);
        $this->assertSame('15000.00', (string) $cierre->costo_productos_vendidos);
        $this->assertSame('120000.00', (string) $cierre->base_diezmo);
        $this->assertSame('12000.00', (string) $cierre->diezmo);
        $this->assertSame('108000.00', (string) $cierre->neto);
    }

    public function test_estado_resultados_usa_la_misma_fuente_que_el_cierre(): void
    {
        $consumo = $this->consumo('2026-11-09', '100000', '30000');
        $producto = $this->producto('300');

        ConsumoServicio::create([
            'consumo_id' => $consumo->id,
            'producto_id' => $producto->id,
            'cantidad' => '100',
            'unidad' => $producto->unidad_control,
            'costo_unitario' => '300',
            'costo_total' => '30000',
            'estado' => 'activo',
            'created_by' => $this->admin->id,
        ]);

        $this->ventaProducto('2026-11-10', '40000', '15000');

        $categoria = CategoriaGasto::firstOrCreate(['nombre' => 'Pruebas cierre'], ['activo' => true]);
        Gasto::create([
            'monto' => '20000',
            'categoria_gasto_id' => $categoria->id,
            'estado' => 'pagado',
            'descripcion' => 'Gasto de prueba',
            'fecha' => '2026-11-11',
            'created_by' => $this->admin->id,
        ]);

        $r = app(ReporteService::class)->estadoResultados('2026-11-09', '2026-11-15');

        $this->assertSame('140000.00', $r['ventas']);
        $this->assertSame('100000.00', $r['ingresos_servicios']);
        $this->assertSame('40000.00', $r['ingresos_productos']);
        $this->assertSame('registro_diario', $r['fuente_servicios']);
        $this->assertSame('30000.00', $r['consumo_inventario']);
        $this->assertSame('15000.00', $r['costo_productos_vendidos']);
        $this->assertSame('20000.00', $r['gastos']);
        $this->assertSame('95000.00', $r['ganancia_bruta']);
        $this->assertSame('75000.00', $r['utilidad_neta']);
    }

    public function test_dashboard_incluye_el_registro_diario_en_las_ventas_de_hoy(): void
    {
        $hoy = Carbon::today()->toDateString();
        $this->consumo($hoy, '100000', '30000');
        $this->ventaProducto($hoy, '40000', '15000');

        $d = app(ReporteService::class)->dashboard();

        $this->assertSame('140000.00', $d['ventas_hoy']);
        $this->assertSame('100000.00', $d['ingresos_hoy']);
    }
}
