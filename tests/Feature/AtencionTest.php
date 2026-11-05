<?php

namespace Tests\Feature;

use App\Models\Atencion;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Registro diario de clientes (atenciones): totales, saldo, anulación y
 * separación del registro de productos.
 */
class AtencionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Pruebas',
            'cedula' => '1111111111',
            'email' => 'atencion@test.local',
            'password' => 'secreto123',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($this->admin);
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'nombre' => 'Cliente '.uniqid(),
            'fecha_nacimiento' => '1990-01-01',
            'celular' => '3000000000',
            'activo' => true,
        ]);
    }

    private function servicio(string $precio): Servicio
    {
        $categoria = \App\Models\CategoriaServicio::firstOrCreate(['nombre' => 'Pruebas Atención']);

        return Servicio::create([
            'categoria_servicio_id' => $categoria->id,
            'nombre' => 'Servicio '.uniqid(),
            'duracion_minutos' => 60,
            'precio' => $precio,
            'activo' => true,
        ]);
    }

    public function test_registra_atencion_con_total_calculado(): void
    {
        $cliente = $this->cliente();
        $s1 = $this->servicio('75000');
        $s2 = $this->servicio('20000');

        $res = $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'pagado' => '50000',
            'detalles' => [
                ['servicio_id' => $s1->id, 'cantidad' => '1', 'precio_unitario' => '75000'],
                ['servicio_id' => $s2->id, 'cantidad' => '1', 'precio_unitario' => '20000'],
            ],
        ]);

        $atencion = Atencion::latest('id')->first();
        $res->assertRedirect(route('atenciones.show', $atencion));

        $this->assertSame(2, $atencion->detalles()->count());
        $this->assertSame('95000.00', $atencion->total);
        $this->assertSame('50000.00', $atencion->pagado);
        $this->assertSame('45000.00', $atencion->saldo());
        $this->assertTrue($atencion->tieneSaldo());
        $this->assertSame(Atencion::ESTADO_ACTIVO, $atencion->estado);
    }

    public function test_permite_servicio_con_descripcion_libre(): void
    {
        $res = $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $this->cliente()->id,
            'detalles' => [
                ['descripcion' => 'Corte + peinado', 'cantidad' => '1', 'precio_unitario' => '40000'],
            ],
        ]);

        $res->assertSessionHasNoErrors();
        $this->assertSame('40000.00', Atencion::latest('id')->first()->total);
    }

    public function test_exige_al_menos_un_servicio_o_descripcion(): void
    {
        $res = $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $this->cliente()->id,
            'detalles' => [
                ['cantidad' => '1', 'precio_unitario' => '1000'],
            ],
        ]);

        $res->assertSessionHasErrors('detalles');
        $this->assertSame(0, Atencion::count());
    }

    public function test_exige_al_menos_una_linea(): void
    {
        $res = $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $this->cliente()->id,
            'detalles' => [],
        ]);

        $res->assertSessionHasErrors('detalles');
    }

    public function test_actualizar_recalcula_total(): void
    {
        $cliente = $this->cliente();
        $s1 = $this->servicio('50000');

        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'detalles' => [['servicio_id' => $s1->id, 'precio_unitario' => '50000']],
        ]);

        $atencion = Atencion::latest('id')->first();

        $this->put('/atenciones/'.$atencion->id, [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'detalles' => [['servicio_id' => $s1->id, 'precio_unitario' => '80000']],
        ]);

        $atencion->refresh();
        $this->assertSame('80000.00', $atencion->total);
        $this->assertSame(1, $atencion->detalles()->count());
    }

    public function test_anular_cambia_estado_y_no_permite_editar(): void
    {
        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $this->cliente()->id,
            'detalles' => [['descripcion' => 'X', 'cantidad' => '1', 'precio_unitario' => '1000']],
        ]);

        $atencion = Atencion::latest('id')->first();

        $this->post('/atenciones/'.$atencion->id.'/anular')->assertRedirect(route('atenciones.show', $atencion));
        $atencion->refresh();

        $this->assertSame(Atencion::ESTADO_ANULADO, $atencion->estado);
        $this->assertNotNull($atencion->reversado_en);

        $this->get('/atenciones/'.$atencion->id.'/editar')->assertStatus(403);
    }

    public function test_pagina_index_y_create_renderizan(): void
    {
        $this->get('/atenciones')->assertStatus(200);
        $this->get('/atenciones/crear')->assertStatus(200);
    }

    public function test_index_filtra_por_cliente(): void
    {
        $cliente = $this->cliente();
        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'detalles' => [['descripcion' => 'Filtro', 'cantidad' => '1', 'precio_unitario' => '1000']],
        ]);

        $res = $this->get('/atenciones?cliente='.$cliente->id);
        $res->assertStatus(200);
        $res->assertSee('Filtro');
    }

    public function test_registro_de_productos_sigue_independiente(): void
    {
        // El registro de productos (consumos) no debe verse afectado por atenciones.
        $this->get('/consumos')->assertStatus(200);
        $this->get('/consumos/registrar')->assertStatus(200);
    }

    public function test_registra_servicio_manual_sin_descripcion(): void
    {
        $res = $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'detalles' => [
                ['servicio_id' => '', 'nombre' => 'Corte + balayage', 'precio_unitario' => '95000'],
            ],
        ]);

        $res->assertSessionHasNoErrors();

        $atencion = Atencion::latest('id')->first();
        $detalle = $atencion->detalles()->first();

        $this->assertNull($detalle->servicio_id);
        $this->assertSame('Corte + balayage', $detalle->nombre);
        $this->assertNull($detalle->descripcion);
        $this->assertSame('95000.00', $atencion->total);
        $this->assertSame('Corte + balayage', $detalle->etiqueta());
    }

    public function test_registra_servicio_del_catalogo_sin_descripcion(): void
    {
        $servicio = $this->servicio('60000');

        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'detalles' => [
                ['servicio_id' => $servicio->id, 'precio_unitario' => '60000'],
            ],
        ])->assertSessionHasNoErrors();

        $detalle = Atencion::latest('id')->first()->detalles()->first();

        $this->assertSame($servicio->id, $detalle->servicio_id);
        $this->assertNull($detalle->descripcion);
    }

    public function test_no_pide_datos_del_cliente(): void
    {
        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'detalles' => [
                ['servicio_id' => '', 'nombre' => 'Lavado', 'precio_unitario' => '35000'],
            ],
        ])->assertSessionHasNoErrors();

        $atencion = Atencion::latest('id')->first();

        $this->assertNull($atencion->cliente_id);
        $this->assertSame('35000.00', $atencion->total);
    }

    public function test_el_formulario_no_pide_cliente_ni_cantidad(): void
    {
        $res = $this->get('/atenciones/crear');

        $res->assertOk()
            ->assertSee('Servicios realizados')
            ->assertSee('Precio (COP)')
            ->assertSee('Descripción (opcional)')
            ->assertDontSee('name="cliente_id"')
            ->assertDontSee('name="cantidad"')
            // Las líneas se envían con nombres numerados (detalles[0][...])
            ->assertSee('detalles[');
    }

    public function test_las_lineas_del_formulario_llegan_al_backend(): void
    {
        // Así es como el navegador envía las líneas del formulario.
        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'detalles' => [
                0 => ['servicio_id' => '', 'nombre' => 'Corte', 'descripcion' => '', 'precio_unitario' => '40000'],
                1 => ['servicio_id' => '', 'nombre' => 'Color', 'descripcion' => 'Matiz 8.3', 'precio_unitario' => '90000'],
            ],
        ])->assertSessionHasNoErrors();

        $atencion = Atencion::latest('id')->first();

        $this->assertSame(2, $atencion->detalles()->count());
        $this->assertSame('130000.00', $atencion->total);
    }

    public function test_el_precio_se_pone_a_mano_en_pesos(): void
    {
        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'pagado' => '200000',
            'detalles' => [
                ['servicio_id' => '', 'nombre' => 'Color completo', 'precio_unitario' => '180000'],
                ['servicio_id' => '', 'nombre' => 'Corte', 'precio_unitario' => '45000'],
            ],
        ])->assertSessionHasNoErrors();

        $atencion = Atencion::latest('id')->first();

        $this->assertSame('225000.00', $atencion->total);
        $this->assertSame('200000.00', $atencion->pagado);
        $this->assertSame('25000.00', $atencion->saldo());

        // La ficha muestra los precios en pesos colombianos.
        $this->get('/atenciones/'.$atencion->id)
            ->assertOk()
            ->assertSee('$225.000')
            ->assertSee('$180.000')
            ->assertSee('$45.000');
    }

    public function test_editar_conserva_el_cliente_que_ya_tenia(): void
    {
        $cliente = $this->cliente();

        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['servicio_id' => '', 'nombre' => 'Corte', 'precio_unitario' => '40000'],
            ],
        ]);

        $atencion = Atencion::latest('id')->first();

        // El formulario ya no pregunta por el cliente: al editar no debe perderse.
        $this->put('/atenciones/'.$atencion->id, [
            'fecha' => now()->toDateString(),
            'detalles' => [
                ['servicio_id' => '', 'nombre' => 'Corte', 'precio_unitario' => '55000'],
            ],
        ])->assertRedirect(route('atenciones.show', $atencion));

        $atencion->refresh();

        $this->assertSame($cliente->id, $atencion->cliente_id);
        $this->assertSame('55000.00', $atencion->total);
    }

    public function test_una_linea_sin_servicio_nombre_ni_descripcion_no_se_guarda(): void
    {
        $this->post('/atenciones', [
            'fecha' => now()->toDateString(),
            'detalles' => [
                ['servicio_id' => '', 'nombre' => '', 'descripcion' => '', 'precio_unitario' => '1000'],
            ],
        ])->assertSessionHasErrors('detalles');

        $this->assertSame(0, Atencion::count());
    }
}
