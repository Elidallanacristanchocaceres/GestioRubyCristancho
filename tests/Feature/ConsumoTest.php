<?php

namespace Tests\Feature;

use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\Consumo;
use App\Models\ConsumoServicio;
use App\Models\Producto;
use App\Models\User;
use App\Services\ConsumoService;
use App\Services\InventarioService;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Consumo de productos por servicio: cálculo, inventario, idempotencia,
 * histórico de costos y anulación.
 *
 * Usa la base de datos de pruebas (beautysalon_testing) dentro de una
 * transacción que siempre se revierte: los datos reales nunca se tocan.
 */
class ConsumoTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Pruebas',
            'cedula' => '0000000000',
            'email' => 'admin@test.local',
            'password' => 'secreto123',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($this->admin);
    }

    private function producto(string $costoUnitario, string $unidad = Producto::UNIDAD_GRAMO): Producto
    {
        $categoria = CategoriaProducto::firstOrCreate(['nombre' => 'Pruebas']);

        return Producto::create([
            'categoria_producto_id' => $categoria->id,
            'nombre' => 'Producto '.$costoUnitario.' '.uniqid(),
            'unidad_control' => $unidad,
            'tipo_uso' => Producto::USO_PROFESIONAL,
            'contenido_envase' => '1000',
            'costo_envase' => '100000',
            'costo_unitario' => $costoUnitario,
            'stock' => '0',
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

    public function test_calcula_costo_total_multiproducto_y_descuenta_stock(): void
    {
        $tinte = $this->producto('450');
        $decolorante = $this->producto('390');
        $this->conStock($tinte, '1000', '450');
        $this->conStock($decolorante, '1000', '390');

        $consumo = app(ConsumoService::class)->registrar([
            'fecha' => '2026-10-15',
            'detalles' => [
                ['producto_id' => $tinte->id, 'cantidad' => '30'],
                ['producto_id' => $decolorante->id, 'cantidad' => '20'],
            ],
        ]);

        // 30 x 450 + 20 x 390 = 13.500 + 7.800 = 21.300
        $this->assertSame('21300.00', $consumo->costo_total);
        $this->assertCount(2, $consumo->detalles);

        $this->assertSame('970.000', $tinte->fresh()->stock);
        $this->assertSame('980.000', $decolorante->fresh()->stock);
    }

    public function test_permite_consumir_sin_stock_y_deja_saldo_negativo(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        // El registro diario no bloquea el trabajo: si se usa más de lo que hay,
        // el consumo se guarda y el stock queda negativo (queda auditado).
        $consumo = app(ConsumoService::class)->registrar([
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '5000']],
        ]);

        $this->assertNotNull($consumo->id);
        $this->assertSame(1, Consumo::count());
        $this->assertSame('-4000.000', $producto->fresh()->stock);
        $this->assertSame('0.000', $producto->lotes()->first()->fresh()->cantidad_disponible);
    }

    public function test_token_idempotente_no_descuenta_dos_veces(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $servicio = app(ConsumoService::class);
        $datos = [
            'token' => 'token-fijo-123',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '10']],
        ];

        $primero = $servicio->registrar($datos);
        $segundo = $servicio->registrar($datos);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, Consumo::count());
        $this->assertSame('990.000', $producto->fresh()->stock);
    }

    public function test_conserva_costo_historico_aunque_cambie_el_producto(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $consumo = app(ConsumoService::class)->registrar([
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '10']],
        ]);
        $this->assertSame('4500.00', $consumo->costo_total);

        // El salón cambia el costo por gramo más adelante.
        $producto->update(['costo_unitario' => '999']);

        $linea = $consumo->detalles()->first();
        $this->assertSame('450.000000', $linea->costo_unitario);
        $this->assertSame('4500.00', $linea->costo_total);
        $this->assertSame('4500.00', $consumo->fresh()->costo_total);

        // El nuevo costo aplica a los próximos consumos.
        $nuevo = app(ConsumoService::class)->registrar([
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '10']],
        ]);
        $this->assertSame('9990.00', $nuevo->costo_total);
    }

    public function test_anular_devuelve_el_stock_y_marca_reversado(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $consumo = app(ConsumoService::class)->registrar([
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ]);
        $this->assertSame('970.000', $producto->fresh()->stock);

        app(ConsumoService::class)->anular($consumo);

        $this->assertSame('1000.000', $producto->fresh()->stock);
        $this->assertSame(Consumo::ESTADO_REVERSADO, $consumo->fresh()->estado);
    }

    public function test_exige_al_menos_un_producto(): void
    {
        $this->expectException(ValidationException::class);
        app(ConsumoService::class)->registrar(['detalles' => []]);
    }

    public function test_falla_si_el_producto_no_tiene_costo_configurado(): void
    {
        $categoria = CategoriaProducto::firstOrCreate(['nombre' => 'Pruebas']);
        $producto = Producto::create([
            'categoria_producto_id' => $categoria->id,
            'nombre' => 'Sin costo',
            'unidad_control' => Producto::UNIDAD_GRAMO,
            'tipo_uso' => Producto::USO_PROFESIONAL,
            'stock' => '0',
            'stock_minimo' => '0',
            'activo' => true,
        ]);
        $this->conStock($producto, '1000', '450');

        $this->expectException(ValidationException::class);
        app(ConsumoService::class)->registrar([
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '10']],
        ]);
    }

    public function test_pantallas_de_consumos_responden(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $consumo = app(ConsumoService::class)->registrar([
            'fecha' => now()->toDateString(),
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ]);

        // 30 x 450 = 13.500 y 30 g usados.
        $this->get('/consumos')->assertOk()->assertSee('13.500')->assertSee('30,000');
        $this->get('/consumos/registrar')->assertOk();
        $this->get('/consumos/'.$consumo->id)->assertOk()->assertSee('13.500');
    }

    public function test_actualizar_revierte_y_reaplica_el_stock(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $consumo = app(ConsumoService::class)->registrar([
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ]);
        $this->assertSame('970.000', $producto->fresh()->stock);

        app(ConsumoService::class)->actualizar($consumo, [
            'fecha' => now()->toDateString(),
            'precio_cobrado' => '50000',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '10']],
        ]);

        // Devuelve los 30 anteriores (1000) y descuenta 10: quedan 990.
        $this->assertSame('990.000', $producto->fresh()->stock);
        $this->assertSame('4500.00', $consumo->fresh()->costo_total);
        $this->assertSame('50000.00', $consumo->fresh()->precio_cobrado);
        $this->assertSame(1, $consumo->detalles()->count());
    }

    public function test_eliminar_devuelve_el_stock_y_borra_el_registro(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $consumo = app(ConsumoService::class)->registrar([
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ]);
        $this->assertSame('970.000', $producto->fresh()->stock);

        app(ConsumoService::class)->eliminar($consumo);

        $this->assertSame('1000.000', $producto->fresh()->stock);
        $this->assertNull(Consumo::find($consumo->id));
        $this->assertSame(0, ConsumoServicio::where('consumo_id', $consumo->id)->count());
    }

    public function test_el_panel_muestra_el_registro_diario(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        app(ConsumoService::class)->registrar([
            'fecha' => now()->toDateString(),
            'precio_cobrado' => '20000',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '10']],
        ]);

        $this->get('/panel')->assertOk()->assertSee('Registro diario de hoy');
    }

    public function test_guardar_producto_individual_crea_cabecera_y_descuenta(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $respuesta = $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-abc',
            'fecha' => now()->toDateString(),
            'descripcion' => 'Color',
            'producto_id' => $producto->id,
            'cantidad' => '30',
        ]);

        $respuesta->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull($respuesta->json('linea.id'));

        $consumo = Consumo::where('token', 'jornada-abc')->first();
        $this->assertNotNull($consumo);
        $this->assertSame('13500.00', $consumo->costo_total);
        $this->assertSame('970.000', $producto->fresh()->stock);
    }

    public function test_guardar_segundo_producto_reutiliza_la_misma_jornada(): void
    {
        $tinte = $this->producto('450');
        $decolorante = $this->producto('390');
        $this->conStock($tinte, '1000', '450');
        $this->conStock($decolorante, '1000', '390');

        $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-xyz', 'fecha' => now()->toDateString(),
            'descripcion' => 'Color', 'producto_id' => $tinte->id, 'cantidad' => '30',
        ])->assertOk();

        $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-xyz', 'fecha' => now()->toDateString(),
            'descripcion' => 'Color', 'producto_id' => $decolorante->id, 'cantidad' => '20',
        ])->assertOk();

        $this->assertSame(1, Consumo::where('token', 'jornada-xyz')->count());
        $consumo = Consumo::where('token', 'jornada-xyz')->first();
        // 30 x 450 + 20 x 390 = 21.300
        $this->assertSame('21300.00', $consumo->costo_total);
        $this->assertSame(2, $consumo->detalles()->count());
    }

    public function test_actualizar_linea_por_detalle_id_reemplaza_y_recalcula(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $lineaId = $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-upd', 'fecha' => now()->toDateString(),
            'descripcion' => 'Color', 'producto_id' => $producto->id, 'cantidad' => '30',
        ])->json('linea.id');

        $this->assertSame('970.000', $producto->fresh()->stock);

        $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-upd', 'fecha' => now()->toDateString(),
            'descripcion' => 'Color', 'producto_id' => $producto->id, 'cantidad' => '10', 'detalle_id' => $lineaId,
        ])->assertOk();

        $consumo = Consumo::where('token', 'jornada-upd')->first();
        $this->assertSame(1, $consumo->detalles()->count());
        $this->assertSame('4500.00', $consumo->costo_total);
        $this->assertSame('990.000', $producto->fresh()->stock);
    }

    public function test_quitar_producto_devuelve_el_stock(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $lineaId = $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-del', 'fecha' => now()->toDateString(),
            'descripcion' => 'Color', 'producto_id' => $producto->id, 'cantidad' => '30',
        ])->json('linea.id');

        $consumo = Consumo::where('token', 'jornada-del')->first();
        $this->assertSame('970.000', $producto->fresh()->stock);

        $this->postJson('/consumos/'.$consumo->id.'/quitar-producto', [
            'detalle_id' => $lineaId,
        ])->assertOk();

        $this->assertSame('1000.000', $producto->fresh()->stock);
        $this->assertSame('0.00', $consumo->fresh()->costo_total);
        $this->assertSame(0, $consumo->detalles()->count());
    }

    public function test_se_puede_registrar_sin_cliente_ni_servicio_y_guarda_lo_que_se_hizo_y_lo_cobrado(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $respuesta = $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-sin-cliente',
            'fecha' => now()->toDateString(),
            'descripcion' => 'Color, corte y cepillado',
            'precio_cobrado' => '85000',
            'producto_id' => $producto->id,
            'cantidad' => '30',
        ]);

        $respuesta->assertOk()->assertJson(['ok' => true]);

        $consumo = Consumo::where('token', 'jornada-sin-cliente')->first();

        $this->assertNotNull($consumo);
        $this->assertNull($consumo->cliente_id);
        $this->assertNull($consumo->servicio_id);
        $this->assertSame('Color, corte y cepillado', $consumo->descripcion);
        $this->assertSame('85000.00', $consumo->precio_cobrado);
        $this->assertSame('970.000', $producto->fresh()->stock);
    }

    public function test_el_formulario_de_registro_no_pide_cliente_ni_servicio(): void
    {
        $this->get('/consumos/registrar')
            ->assertOk()
            ->assertSee('Qué hicimos')
            ->assertSee('Cobrado')
            ->assertDontSee('name="cliente_id"')
            ->assertDontSee('name="servicio_id"');
    }

    public function test_el_listado_permite_eliminar_un_registro_y_devuelve_el_stock(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $consumo = app(ConsumoService::class)->registrar([
            'fecha' => now()->toDateString(),
            'descripcion' => 'Color mal registrado',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ]);
        $this->assertSame('970.000', $producto->fresh()->stock);

        // El listado muestra el botón para eliminar y el registro se borra.
        $this->get('/consumos')->assertOk()->assertSee('Eliminar');

        $this->delete('/consumos/'.$consumo->id)
            ->assertRedirect(route('consumos.index'))
            ->assertSessionHas('exito');

        $this->assertNull(Consumo::find($consumo->id));
        $this->assertSame('1000.000', $producto->fresh()->stock);
    }

    public function test_editar_un_registro_conserva_el_cliente_que_ya_tenia(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $cliente = Cliente::create([
            'nombre' => 'Cliente '.uniqid(),
            'fecha_nacimiento' => '1990-01-01',
            'celular' => '3000000000',
            'activo' => true,
        ]);

        $consumo = app(ConsumoService::class)->registrar([
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'descripcion' => 'Color',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ]);

        // El formulario ya no pregunta por el cliente: al editar no debe borrarse.
        $this->put('/consumos/'.$consumo->id, [
            'fecha' => now()->toDateString(),
            'descripcion' => 'Color y corte',
            'precio_cobrado' => '60000',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ])->assertRedirect(route('consumos.show', $consumo));

        $consumo->refresh();

        $this->assertSame($cliente->id, $consumo->cliente_id);
        $this->assertSame('Color y corte', $consumo->descripcion);
        $this->assertSame('60000.00', $consumo->precio_cobrado);
    }

    public function test_no_se_guarda_un_producto_sin_saber_que_se_hizo(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-sin-descripcion',
            'fecha' => now()->toDateString(),
            'producto_id' => $producto->id,
            'cantidad' => '30',
        ])->assertStatus(422);

        $this->assertSame(0, Consumo::count());
        $this->assertSame('1000.000', $producto->fresh()->stock);
    }

    public function test_se_puede_guardar_la_jornada_sin_registrar_mas_productos(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $consumo = app(ConsumoService::class)->registrar([
            'fecha' => now()->toDateString(),
            'descripcion' => 'Color',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => '30']],
        ]);

        $this->patchJson('/consumos/'.$consumo->id.'/cabecera', [
            'fecha' => now()->toDateString(),
            'descripcion' => 'Color 8.3 + balayage',
            'precio_cobrado' => '120000',
            'observaciones' => 'Fórmula guardada en la ficha',
        ])->assertOk()->assertJson(['ok' => true]);

        $consumo->refresh();

        $this->assertSame('Color 8.3 + balayage', $consumo->descripcion);
        $this->assertSame('120000.00', $consumo->precio_cobrado);
        $this->assertSame('Fórmula guardada en la ficha', $consumo->observaciones);
        // No se toca el inventario ni las líneas ya registradas.
        $this->assertSame(1, $consumo->detalles()->count());
        $this->assertSame('970.000', $producto->fresh()->stock);
    }

    public function test_agregar_un_producto_no_borra_lo_que_ya_esta_registrado(): void
    {
        $producto = $this->producto('450');
        $this->conStock($producto, '1000', '450');

        $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-doble', 'fecha' => now()->toDateString(),
            'descripcion' => 'Color', 'precio_cobrado' => '80000',
            'producto_id' => $producto->id, 'cantidad' => '30',
        ])->assertOk();

        // Un guardado que no vuelve a mandar la cabecera no debe vaciarla.
        $this->postJson('/consumos/guardar-producto', [
            'token' => 'jornada-doble', 'fecha' => now()->toDateString(),
            'descripcion' => 'Color', 'producto_id' => $producto->id, 'cantidad' => '10',
        ])->assertOk();

        $consumo = Consumo::where('token', 'jornada-doble')->first();

        $this->assertSame('Color', $consumo->descripcion);
        $this->assertSame('80000.00', $consumo->precio_cobrado);
        $this->assertSame(2, $consumo->detalles()->count());
    }
}
