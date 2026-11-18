<?php

namespace Tests\Feature;

use App\Models\CategoriaProducto;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\User;
use App\Services\InventarioService;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Inventario: unidad bolsa, envases nuevos vs. empezados, medición,
 * carga inicial idempotente y panel de revisión de precios.
 *
 * La interfaz Blade se eliminó; estas pruebas ejercitan la API JSON que
 * consume Angular y conservan las pruebas de servicio.
 */
class InventarioTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private CategoriaProducto $categoria;

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

        $this->categoria = CategoriaProducto::create([
            'nombre' => 'Categoría pruebas '.uniqid(),
            'grupo' => CategoriaProducto::GRUPO_OTROS,
        ]);

        Sanctum::actingAs($this->admin);
    }

    private function producto(array $atributos = []): Producto
    {
        return Producto::create(array_merge([
            'categoria_producto_id' => $this->categoria->id,
            'nombre' => 'Producto prueba '.uniqid(),
            'unidad_control' => Producto::UNIDAD_MILILITRO,
            'stock' => 0,
            'stock_minimo' => 0,
            'activo' => true,
        ], $atributos));
    }

    public function test_un_envase_empezado_no_suma_stock_pero_queda_registrado(): void
    {
        $producto = $this->producto(['contenido_envase' => 1000, 'costo_unitario' => 186]);

        $lote = app(InventarioService::class)->registrarEntrada($producto, '0', '0', [
            'tipo' => InventarioService::TIPO_ENTRADA,
            'envases' => 1,
            'estado_envase' => 'empezado',
            'medido' => false,
            'motivo' => 'Entrada de inventario (envase empezado por medir)',
        ]);

        $producto->refresh();

        $this->assertSame('0.000', $producto->stock);
        $this->assertFalse($lote->medido);
        $this->assertSame('empezado', $lote->estado_envase);
        $this->assertSame('1.000', $lote->envases);
        $this->assertSame('pendiente_medicion', $lote->estado);
        $this->assertTrue($lote->estaPendienteDeMedicion());
    }

    public function test_medir_un_envase_sumable_las_existencias_y_deja_movimiento(): void
    {
        $producto = $this->producto(['contenido_envase' => 1000, 'costo_unitario' => 186]);

        $lote = app(InventarioService::class)->registrarEntrada($producto, '0', '0', [
            'envases' => 1,
            'estado_envase' => 'empezado',
            'medido' => false,
        ]);

        $this->assertSame('0.000', $producto->refresh()->stock);

        app(InventarioService::class)->medirEnvase($lote, '480');

        $producto->refresh();
        $lote->refresh();

        $this->assertSame('480.000', $producto->stock);
        $this->assertTrue($lote->medido);
        $this->assertSame('480.000', $lote->cantidad_disponible);

        $movimiento = InventarioMovimiento::where('producto_id', $producto->id)
            ->where('tipo', InventarioService::TIPO_AJUSTE)
            ->latest('id')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame('480.000', $movimiento->cantidad);
        $this->assertSame('480.000', $movimiento->stock_nuevo);
    }

    public function test_medir_un_envase_rechaza_cantidades_negativas(): void
    {
        $producto = $this->producto(['contenido_envase' => 1000]);

        $lote = app(InventarioService::class)->registrarEntrada($producto, '0', '0', [
            'envases' => 1,
            'medido' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(InventarioService::class)->medirEnvase($lote, '-5');
    }

    public function test_la_entrada_rechaza_cantidad_cero_cuando_el_envase_es_nuevo(): void
    {
        $producto = $this->producto(['contenido_envase' => 1000]);

        $this->expectException(ValidationException::class);

        app(InventarioService::class)->registrarEntrada($producto, '0', '0', [
            'medido' => true,
        ]);
    }

    public function test_la_api_registra_un_envase_empezado(): void
    {
        $producto = $this->producto(['contenido_envase' => 950, 'costo_unitario' => 186]);

        $this->postJson('/api/entradas', [
            'producto_id' => $producto->id,
            'cantidad_envases' => 2,
            'estado_envase' => 'empezado',
            'observaciones' => 'Recibido abierto',
        ])->assertCreated();

        $producto->refresh();

        $this->assertSame('0.000', $producto->stock);
        $this->assertSame(1, InventarioLote::where('producto_id', $producto->id)->count());
        $this->assertSame(
            1,
            InventarioLote::where('producto_id', $producto->id)->where('medido', false)->count()
        );
    }

    public function test_la_api_suma_el_contenido_de_los_envases_nuevos(): void
    {
        $producto = $this->producto(['contenido_envase' => 950, 'costo_unitario' => 186]);

        $this->postJson('/api/entradas', [
            'producto_id' => $producto->id,
            'cantidad_envases' => 3,
            'contenido_envase' => 950,
            'estado_envase' => 'nuevo',
        ])->assertCreated();

        $producto->refresh();

        $this->assertSame('2850.000', $producto->stock);
    }

    public function test_la_medicion_por_api_suma_el_contenido_real_al_stock(): void
    {
        $producto = $this->producto(['contenido_envase' => 950, 'costo_unitario' => 186]);

        $this->postJson('/api/entradas', [
            'producto_id' => $producto->id,
            'cantidad_envases' => 1,
            'estado_envase' => 'empezado',
        ])->assertCreated();

        $lote = InventarioLote::where('producto_id', $producto->id)->firstOrFail();

        $this->assertSame('0.000', $producto->refresh()->stock);

        $this->postJson('/api/productos/'.$producto->id.'/medir', [
            'lote_id' => $lote->id,
            'cantidad' => 620,
        ])->assertOk();

        $producto->refresh();
        $lote->refresh();

        $this->assertSame('620.000', $producto->stock);
        $this->assertTrue($lote->medido);
    }

    public function test_la_medicion_por_api_rechaza_un_lote_ajeno_al_producto(): void
    {
        $producto = $this->producto(['contenido_envase' => 950]);
        $otro = $this->producto(['contenido_envase' => 950]);

        $loteAjeno = app(InventarioService::class)->registrarEntrada($otro, '0', '0', [
            'envases' => 1,
            'medido' => false,
        ]);

        $this->postJson('/api/productos/'.$producto->id.'/medir', [
            'lote_id' => $loteAjeno->id,
            'cantidad' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('lote_id');
    }

    public function test_la_unidad_bolsa_se_puede_guardar_y_se_muestra_en_la_presentacion(): void
    {
        $categoria = CategoriaProducto::create([
            'nombre' => 'Categoría decolorantes '.uniqid(),
            'grupo' => CategoriaProducto::GRUPO_DECOLORANTES,
        ]);

        $producto = Producto::create([
            'categoria_producto_id' => $categoria->id,
            'nombre' => 'Deco White prueba',
            'unidad_control' => Producto::UNIDAD_BOLSA,
            'presentacion' => '1000 g',
            'contenido_envase' => 1000,
            'stock' => 0,
            'activo' => true,
        ]);

        $this->assertTrue($producto->esBolsa());
        $this->assertSame('bolsas', $producto->unidadLabel());
        $this->assertSame('1000 g', $producto->presentacionLabel());

        $this->postJson('/api/productos', [
            'categoria_producto_id' => $categoria->id,
            'nombre' => 'Producto con bolsa',
            'unidad_control' => Producto::UNIDAD_BOLSA,
            'presentacion' => 'bolsa 500 g',
            'contenido_envase' => 500,
        ])->assertCreated();

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto con bolsa',
            'unidad_control' => Producto::UNIDAD_BOLSA,
            'presentacion' => 'bolsa 500 g',
        ]);
    }

    public function test_la_ficha_por_api_muestra_los_envases_pendientes_de_medicion(): void
    {
        $producto = $this->producto(['contenido_envase' => 950, 'costo_unitario' => 186]);

        app(InventarioService::class)->registrarEntrada($producto, '0', '0', [
            'envases' => 2,
            'estado_envase' => 'empezado',
            'medido' => false,
            'notas' => 'Envase abierto, sin medir',
        ]);

        $this->getJson('/api/productos/'.$producto->id)
            ->assertOk()
            ->assertJsonPath('lotes.0.medido', false)
            ->assertJsonPath('lotes.0.estado', 'pendiente_medicion')
            ->assertJsonPath('lotes.0.notas', 'Envase abierto, sin medir');
    }

    public function test_el_listado_por_api_filtra_por_grupo_y_por_productos_por_medir(): void
    {
        $this->getJson('/api/productos?grupo='.CategoriaProducto::GRUPO_AMPOLLAS)
            ->assertOk();

        $this->getJson('/api/productos?por_medir=1')
            ->assertOk();
    }

    public function test_el_panel_de_revision_de_precios_por_api_responde_y_no_modifica_datos(): void
    {
        $producto = $this->producto([
            'contenido_envase' => 100,
            'costo_unitario' => 10,
            'precio_venta' => 9999,
        ]);

        $this->getJson('/api/productos/revision-precios')
            ->assertOk()
            ->assertJsonStructure(['data', 'pendientes', 'total']);

        $producto->refresh();

        $this->assertSame('9999.00', $producto->precio_venta);
    }

    public function test_la_exportacion_csv_incluye_la_presentacion(): void
    {
        $producto = $this->producto([
            'contenido_envase' => 950,
            'costo_unitario' => 186,
            'precio_venta' => 20000,
        ]);

        $respuesta = $this->get('/api/productos/exportar');

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $respuesta->streamedContent();

        $this->assertStringContainsString($producto->nombre, $csv);
        $this->assertStringContainsString('950,000 ml/envase', $csv);
    }

    public function test_la_api_ajusta_reactiva_y_registra_consumo(): void
    {
        $producto = $this->producto(['contenido_envase' => 1000, 'costo_unitario' => 186]);

        $this->postJson('/api/productos/'.$producto->id.'/ajustar', [
            'nueva_cantidad' => '100',
            'motivo' => 'Conteo físico',
        ])->assertOk();

        $this->assertSame('100.000', $producto->refresh()->stock);

        $this->postJson('/api/productos/'.$producto->id.'/consumo', [
            'cantidad' => '10',
            'motivo' => 'Servicio de prueba',
        ])->assertOk();

        $this->assertSame('90.000', $producto->refresh()->stock);

        $producto->update(['activo' => false]);

        $this->postJson('/api/productos/'.$producto->id.'/reactivar')
            ->assertOk();

        $this->assertTrue($producto->refresh()->activo);
    }

    public function test_la_carga_inicial_es_idempotente_no_duplica_productos_ni_lotes(): void
    {
        Artisan::call('inventario:importar');

        $productos = Producto::count();
        $lotes = InventarioLote::count();
        $movimientos = InventarioMovimiento::count();

        $this->assertGreaterThan(0, $productos);

        Artisan::call('inventario:importar');

        $this->assertSame($productos, Producto::count());
        $this->assertSame($lotes, InventarioLote::count());
        $this->assertSame($movimientos, InventarioMovimiento::count());
    }

    public function test_la_carga_inicial_en_simulacion_no_escribe_nada(): void
    {
        $antesProductos = Producto::count();
        $antesLotes = InventarioLote::count();

        Artisan::call('inventario:importar', ['--dry-run' => true]);

        $this->assertSame($antesProductos, Producto::count());
        $this->assertSame($antesLotes, InventarioLote::count());
    }

    public function test_la_carga_inicial_deja_los_envases_sin_medir_pendientes(): void
    {
        Artisan::call('inventario:importar');

        $pendientes = InventarioLote::where('medido', false)->count();

        $this->assertGreaterThan(0, $pendientes);

        // Ningún envase pendiente puede haber sumado existencias.
        foreach (InventarioLote::where('medido', false)->get() as $lote) {
            $this->assertSame('0.000', $lote->cantidad_disponible);
            $this->assertSame('pendiente_medicion', $lote->estado);
        }
    }

    public function test_el_costo_consumido_usa_el_costo_por_unidad_del_producto(): void
    {
        $producto = $this->producto([
            'unidad_control' => Producto::UNIDAD_GRAMO,
            'contenido_envase' => 500,
            'costo_unitario' => 450,
        ]);

        $inventario = app(InventarioService::class);

        $inventario->registrarEntrada($producto, '500', Money::mul('450', '500'), [
            'costo_unitario' => '450',
        ]);

        $resultado = $inventario->consumir($producto, '30', ['motivo' => 'Servicio de prueba']);

        $this->assertSame('13500.00', $resultado['costo_total']);
        $this->assertSame('470.000', $producto->refresh()->stock);
    }
}
