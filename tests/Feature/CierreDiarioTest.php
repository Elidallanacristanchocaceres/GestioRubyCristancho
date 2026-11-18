<?php

namespace Tests\Feature;

use App\Models\CategoriaGasto;
use App\Models\CategoriaProducto;
use App\Models\CierreDiario;
use App\Models\ConsumoServicio;
use App\Models\Gasto;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\User;
use App\Models\VentaDetalle;
use App\Services\CierreDiarioService;
use App\Services\ConsumoService;
use App\Services\InventarioService;
use App\Services\RegistroDiarioService;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Control financiero diario: servicios, productos usados, productos vendidos,
 * gastos del salón/casa, cierre, reapertura e histórico.
 */
class CierreDiarioTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Cierre Diario',
            'cedula' => '9999999999',
            'email' => 'cierre-diario-'.uniqid().'@test.local',
            'password' => 'secreto123',
            'role' => User::ROLE_ADMIN,
        ]);

        Sanctum::actingAs($this->admin);
    }

    private function producto(string $costo, string $stock = '0'): Producto
    {
        $categoria = CategoriaProducto::create([
            'nombre' => 'Categoría '.uniqid(),
            'grupo' => 'tintes',
        ]);

        return Producto::create([
            'categoria_producto_id' => $categoria->id,
            'nombre' => 'Producto '.uniqid(),
            'unidad_control' => Producto::UNIDAD_GRAMO,
            'presentacion' => 'Pote 400 g',
            'contenido_envase' => '400',
            'costo_envase' => '180000',
            'costo_unitario' => $costo,
            'precio_venta' => '30000',
            'stock' => $stock,
            'stock_minimo' => '0',
            'activo' => true,
        ]);
    }

    private function conStock(Producto $producto, string $cantidad, string $costoPorUnidad): void
    {
        app(InventarioService::class)->registrarEntrada(
            $producto,
            $cantidad,
            Money::mul($costoPorUnidad, $cantidad),
            [
                'tipo' => InventarioService::TIPO_ENTRADA,
                'costo_unitario' => $costoPorUnidad,
                'motivo' => 'Carga de prueba',
            ]
        );
    }

    private function atencion(string $fecha, string $monto): void
    {
        $this->postJson('/api/atenciones', [
            'fecha' => $fecha,
            'detalles' => [
                ['nombre' => 'Servicio '.uniqid(), 'cantidad' => '1', 'precio_unitario' => $monto],
            ],
        ])->assertCreated();
    }

    private function consumo(string $fecha, Producto $producto, string $gramos): void
    {
        app(ConsumoService::class)->registrar([
            'fecha' => $fecha,
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => $gramos],
            ],
        ]);
    }

    private function ventaProducto(string $fecha, Producto $producto, string $cantidad, string $precio): void
    {
        $this->postJson('/api/productos-vendidos', [
            'fecha' => $fecha,
            'pagar' => false,
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                ],
            ],
        ])->assertCreated();
    }

    private function gasto(string $fecha, string $ambito, string $monto): Gasto
    {
        $categoria = CategoriaGasto::create([
            'nombre' => 'Categoría '.$ambito.' '.uniqid(),
            'ambito' => $ambito,
            'activo' => true,
        ]);

        $this->postJson('/api/gastos', [
            'fecha' => $fecha,
            'monto' => $monto,
            'ambito' => $ambito,
            'categoria_gasto_id' => $categoria->id,
            'descripcion' => 'Gasto de prueba '.$ambito,
            'estado' => 'pagado',
            'afectar_caja' => false,
        ])->assertCreated();

        return Gasto::latest('id')->first();
    }

    private function resumen(string $fecha): array
    {
        return app(CierreDiarioService::class)->resumen($fecha);
    }

    public function test_01_catalogo_calcula_y_permite_configurar_valor_por_gramo(): void
    {
        $categoria = CategoriaProducto::create([
            'nombre' => 'Catálogo '.uniqid(),
            'grupo' => 'tintes',
        ]);

        $producto = Producto::create([
            'categoria_producto_id' => $categoria->id,
            'nombre' => 'Tinte de prueba',
            'unidad_control' => Producto::UNIDAD_GRAMO,
            'presentacion' => 'Pote 400 g',
            'contenido_envase' => '400',
            'costo_envase' => '22000',
            'stock' => '0',
            'stock_minimo' => '0',
            'activo' => true,
        ]);

        $producto->recalcularCostoUnitario();
        $producto->save();

        // 22.000 / 400 = 55 por gramo.
        $this->assertSame('55.000000', $producto->fresh()->costo_unitario);
        $this->assertSame('55.000000', RegistroDiarioService::costoPorGramo($producto->fresh()));

        // El usuario puede corregir el valor de la ficha y manda sobre el cálculo.
        $producto->update(['costo_unitario' => '75.500000']);
        $this->assertSame('75.500000', RegistroDiarioService::costoPorGramo($producto->fresh()));
    }

    public function test_02_varios_registros_del_mismo_producto_acumulan_gramos_y_costo(): void
    {
        $fecha = '2026-10-20';
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $this->consumo($fecha, $producto, '30');
        $this->consumo($fecha, $producto, '20');

        $resumen = $this->resumen($fecha);

        $this->assertCount(1, $resumen['productos_utilizados']);
        $this->assertSame('50.000', $resumen['productos_utilizados'][0]['cantidad']);
        $this->assertSame('22500.00', $resumen['total_productos_utilizados']);
        $this->assertCount(2, $resumen['productos_utilizados'][0]['lineas']);
    }

    public function test_03_cada_producto_usa_su_propio_valor_por_gramo(): void
    {
        $fecha = '2026-10-21';
        $tinte = $this->producto('450');
        $decolorante = $this->producto('390');
        $this->conStock($tinte, '1000', '450');
        $this->conStock($decolorante, '1000', '390');

        $this->consumo($fecha, $tinte, '10');
        $this->consumo($fecha, $decolorante, '10');

        $resumen = $this->resumen($fecha);

        // 10 x 450 + 10 x 390 = 8.400. No se mezclan gramos entre productos.
        $this->assertSame('8400.00', $resumen['total_productos_utilizados']);
        $this->assertSame('4500.00', $resumen['productos_utilizados'][0]['costo']);
        $this->assertSame('3900.00', $resumen['productos_utilizados'][1]['costo']);
    }

    public function test_04_el_valor_del_gramo_queda_congelado_en_el_cierre(): void
    {
        $fecha = '2026-10-22';
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $this->consumo($fecha, $producto, '10');

        $cierre = app(CierreDiarioService::class)->cerrar($fecha);

        $this->assertSame('4500.00', $cierre->total_productos_utilizados);
        $this->assertSame('450.000000', $cierre->detalle['productos_utilizados'][0]['lineas'][0]['costo_unitario']);

        // Cambiar el precio de la ficha no altera el cierre histórico.
        $producto->update(['costo_unitario' => '999']);

        $cierre->refresh();
        $this->assertSame('4500.00', $cierre->total_productos_utilizados);
        $this->assertSame('4500.00', $this->resumen($fecha)['total_productos_utilizados']);
    }

    public function test_05_los_servicios_del_dia_se_suman_por_su_valor_cobrado(): void
    {
        $fecha = '2026-10-23';

        $this->atencion($fecha, '80000');
        $this->atencion($fecha, '45000');

        $resumen = $this->resumen($fecha);

        $this->assertSame('125000.00', $resumen['total_servicios']);
        $this->assertCount(2, $resumen['servicios']);
        $this->assertSame('atenciones', $resumen['fuente_servicios']);
    }

    public function test_06_productos_vendidos_son_independientes_y_guardan_presentacion_y_costo(): void
    {
        $fecha = '2026-10-24';
        $producto = $this->producto('1000');
        $this->conStock($producto, '10', '1000');

        $this->ventaProducto($fecha, $producto, '2', '30000');

        $resumen = $this->resumen($fecha);

        $this->assertSame('60000.00', $resumen['total_productos_vendidos']);
        $this->assertSame('2000.00', $resumen['costo_productos_vendidos']);
        $this->assertCount(0, $resumen['productos_utilizados']);
        $this->assertSame('Pote 400 g', $resumen['productos_vendidos'][0]['presentacion']);
        $this->assertSame('400.000', $resumen['productos_vendidos'][0]['contenido']);
        $this->assertSame('8.000', $producto->fresh()->stock);

        $detalle = VentaDetalle::where('producto_id', $producto->id)->first();
        $this->assertSame('Pote 400 g', $detalle->presentacion);
        $this->assertSame('400.000', $detalle->contenido_envase);

        // La presentación de la venta queda congelada aunque cambie la ficha.
        $producto->update(['presentacion' => 'Presentación nueva', 'contenido_envase' => '500']);
        $this->assertSame('Pote 400 g', $this->resumen($fecha)['productos_vendidos'][0]['presentacion']);
        $this->assertSame('400.000', $this->resumen($fecha)['productos_vendidos'][0]['contenido']);
    }

    public function test_07_el_costo_de_lo_vendido_no_se_resta_dos_veces_en_la_formula(): void
    {
        $fecha = '2026-10-25';
        $producto = $this->producto('1000');
        $this->conStock($producto, '10', '1000');

        $this->ventaProducto($fecha, $producto, '2', '30000');

        $resumen = $this->resumen($fecha);

        // La fórmula diaria resta productos utilizados y gastos, no el costo
        // de adquisición de lo vendido. Ese costo queda como dato informativo.
        $this->assertSame('60000.00', $resumen['resultado']);
        $this->assertSame('2000.00', $resumen['costo_productos_vendidos']);
        $this->assertSame('0.00', $resumen['total_productos_utilizados']);
    }

    public function test_08_gastos_del_salon_y_de_la_casa_quedan_separados(): void
    {
        $fecha = '2026-10-26';
        $this->gasto($fecha, Gasto::AMBITO_SALON, '20000');
        $this->gasto($fecha, Gasto::AMBITO_CASA, '15000');

        $resumen = $this->resumen($fecha);

        $this->assertSame('20000.00', $resumen['total_gastos_salon']);
        $this->assertSame('15000.00', $resumen['total_gastos_casa']);
        $this->assertCount(1, $resumen['gastos_salon']);
        $this->assertCount(1, $resumen['gastos_casa']);
        $this->assertSame('Casa', $resumen['gastos_casa'][0]['ambito_label']);

        $this->getJson('/api/gastos?ambito=casa')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total_casa', '15000.00');
    }

    public function test_09_el_cierre_calcula_las_cinco_secciones_y_la_formula_exacta(): void
    {
        $fecha = '2026-10-27';
        $utilizado = $this->producto('450');
        $vendido = $this->producto('1000');
        $this->conStock($utilizado, '1000', '450');
        $this->conStock($vendido, '10', '1000');

        $this->atencion($fecha, '100000');
        $this->consumo($fecha, $utilizado, '10');
        $this->ventaProducto($fecha, $vendido, '1', '30000');
        $this->gasto($fecha, Gasto::AMBITO_SALON, '10000');
        $this->gasto($fecha, Gasto::AMBITO_CASA, '4000');

        $resumen = $this->resumen($fecha);

        $this->assertSame('100000.00', $resumen['total_servicios']);
        $this->assertSame('30000.00', $resumen['total_productos_vendidos']);
        $this->assertSame('4500.00', $resumen['total_productos_utilizados']);
        $this->assertSame('10000.00', $resumen['total_gastos_salon']);
        $this->assertSame('4000.00', $resumen['total_gastos_casa']);
        // 100.000 + 30.000 - 4.500 - 10.000 - 4.000 = 111.500
        $this->assertSame('111500.00', $resumen['resultado']);
        $this->assertSame('Saldo después de los movimientos registrados', $resumen['resultado_label']);
    }

    public function test_10_cerrar_dia_guarda_foto_y_evita_cierre_duplicado(): void
    {
        $fecha = '2026-10-28';
        $this->atencion($fecha, '70000');

        $this->postJson('/api/cierres-diarios', [
            'fecha' => $fecha,
            'notas' => 'Cierre de prueba',
        ])->assertCreated()
            ->assertJsonPath('data.estado', 'cerrado')
            ->assertJsonPath('data.total_servicios', '70000.00');

        $cierre = CierreDiario::whereDate('fecha', $fecha)->first();
        $this->assertNotNull($cierre);
        $this->assertSame('70000.00', $cierre->total_servicios);
        $this->assertSame('Cierre de prueba', $cierre->notas);
        $this->assertSame('70000.00', $cierre->detalle['total_servicios']);

        $this->postJson('/api/cierres-diarios', ['fecha' => $fecha])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $this->assertSame(1, CierreDiario::whereDate('fecha', $fecha)->count());
    }

    public function test_11_reapertura_controlada_conserva_historial_y_permite_cerrar_de_nuevo(): void
    {
        $fecha = '2026-10-29';
        $this->atencion($fecha, '50000');

        $this->postJson('/api/cierres-diarios', ['fecha' => $fecha])->assertCreated();
        $primero = CierreDiario::whereDate('fecha', $fecha)->latest('id')->first();

        $this->postJson('/api/cierres-diarios/'.$primero->id.'/reabrir', [
            'motivo' => 'Se omitió un servicio',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'reabierto')
            ->assertJsonPath('data.motivo_reapertura', 'Se omitió un servicio');

        $this->atencion($fecha, '25000');

        $this->postJson('/api/cierres-diarios', ['fecha' => $fecha])->assertCreated();

        $this->assertSame(2, CierreDiario::whereDate('fecha', $fecha)->count());
        $this->assertSame('reabierto', $primero->fresh()->estado);
        $this->assertSame('50000.00', $primero->fresh()->total_servicios);

        $segundo = CierreDiario::whereDate('fecha', $fecha)->where('estado', 'cerrado')->first();
        $this->assertNotNull($segundo);
        $this->assertSame('75000.00', $segundo->total_servicios);
    }

    public function test_12_historico_filtra_por_fechas_y_agrupa_semana_y_mes(): void
    {
        $dia1 = '2026-11-10';
        $dia2 = '2026-11-11';

        $this->atencion($dia1, '10000');
        $this->atencion($dia2, '25000');

        app(CierreDiarioService::class)->cerrar($dia1);
        app(CierreDiarioService::class)->cerrar($dia2);

        $this->getJson('/api/cierres-diarios/historial?desde='.$dia1.'&hasta='.$dia2)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('totales.total_servicios', '35000.00')
            ->assertJsonCount(1, 'por_semana')
            ->assertJsonCount(1, 'por_mes');
    }

    public function test_13_valida_cantidades_precios_y_montos_antes_de_guardar(): void
    {
        $fecha = '2026-10-30';
        $producto = $this->producto('1000');
        $this->conStock($producto, '10', '1000');

        $this->postJson('/api/productos-vendidos', [
            'fecha' => $fecha,
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => '0', 'precio_unitario' => '30000'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('detalles.0.cantidad');

        $this->postJson('/api/gastos', [
            'fecha' => $fecha,
            'monto' => '0',
            'ambito' => Gasto::AMBITO_SALON,
            'descripcion' => 'Monto inválido',
            'estado' => 'pagado',
            'afectar_caja' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('monto');

        $this->assertSame('10.000', $producto->fresh()->stock);
    }

    public function test_14_la_venta_de_producto_solo_mueve_inventario_una_vez(): void
    {
        $fecha = '2026-10-31';
        $producto = $this->producto('1000');
        $this->conStock($producto, '10', '1000');

        $this->ventaProducto($fecha, $producto, '3', '30000');

        $this->assertSame('7.000', $producto->fresh()->stock);

        // No se crea un consumo de gramos ni una salida duplicada.
        $this->assertSame(0, ConsumoServicio::where('producto_id', $producto->id)->count());
        $this->assertSame(1, InventarioMovimiento::where('producto_id', $producto->id)
            ->where('tipo', InventarioService::TIPO_VENTA)->count());
    }

    public function test_15_un_dia_cerrado_bloquea_ediciones_hasta_reabrirlo(): void
    {
        $fecha = '2026-11-01';
        $this->atencion($fecha, '40000');

        $producto = $this->producto('1000');
        $this->conStock($producto, '100', '1000');
        $consumo = app(ConsumoService::class)->registrar([
            'fecha' => $fecha,
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => '10'],
            ],
        ]);

        $this->postJson('/api/cierres-diarios', ['fecha' => $fecha])->assertCreated();

        $this->postJson('/api/atenciones', [
            'fecha' => $fecha,
            'detalles' => [
                ['nombre' => 'Servicio posterior', 'cantidad' => '1', 'precio_unitario' => '10000'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('fecha');

        $this->postJson('/api/gastos', [
            'fecha' => $fecha,
            'monto' => '5000',
            'ambito' => Gasto::AMBITO_SALON,
            'descripcion' => 'Gasto posterior al cierre',
            'estado' => 'pagado',
            'afectar_caja' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('fecha');

        $this->deleteJson('/api/consumos/'.$consumo->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $cierre = CierreDiario::whereDate('fecha', $fecha)->first();
        $this->postJson('/api/cierres-diarios/'.$cierre->id.'/reabrir', [
            'motivo' => 'Corrección autorizada',
        ])->assertOk();

        $this->deleteJson('/api/consumos/'.$consumo->id)->assertOk();

        $this->postJson('/api/atenciones', [
            'fecha' => $fecha,
            'detalles' => [
                ['nombre' => 'Servicio corregido', 'cantidad' => '1', 'precio_unitario' => '10000'],
            ],
        ])->assertCreated();
    }
}
