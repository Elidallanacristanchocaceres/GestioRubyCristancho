<?php

namespace Tests\Feature;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * API JSON para el frontend Angular (Sanctum SPA).
 * Usa la base de pruebas beautysalon_testing con DatabaseTransactions.
 */
class ApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private CategoriaProducto $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Pruebas Api',
            'cedula' => '0000000000',
            'email' => 'adminapi@test.local',
            'password' => 'secreto123',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->categoria = CategoriaProducto::create([
            'nombre' => 'Categoría API '.uniqid(),
            'grupo' => CategoriaProducto::GRUPO_OTROS,
        ]);
    }

    private function producto(array $atributos = []): Producto
    {
        return Producto::create(array_merge([
            'categoria_producto_id' => $this->categoria->id,
            'nombre' => 'Producto API '.uniqid(),
            'unidad_control' => Producto::UNIDAD_MILILITRO,
            'stock' => '100.000',
            'stock_minimo' => '10.000',
            'activo' => true,
        ], $atributos));
    }

    public function test_login_me_dashboard_y_productos(): void
    {
        $producto = $this->producto();

        $this->withSession(['_token' => 'test-token']);

        $this->postJson('/api/login', [
            'email' => 'adminapi@test.local',
            'password' => 'secreto123',
        ])->assertOk()->assertJsonPath('user.email', 'adminapi@test.local');

        $this->getJson('/api/me')->assertOk();

        $this->getJson('/api/dashboard')->assertOk()->assertJsonStructure([
            'ventas_hoy', 'ingresos_mes', 'serie', 'estado_resultados_mes',
        ]);

        $this->getJson('/api/productos')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['nombre' => $producto->nombre]);

        $this->getJson('/api/productos/'.$producto->id)->assertOk()
            ->assertJsonPath('data.nombre', $producto->nombre);
    }

    public function test_invitado_no_accede_a_la_api(): void
    {
        $this->getJson('/api/productos')->assertUnauthorized();
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_documentacion_tipo_swagger_esta_disponible(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('SwaggerUIBundle');

        $this->getJson('/docs/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('paths./api/productos.get.summary', 'api.productos.index')
            ->assertJsonPath('paths./api/login.post.summary', 'POST /api/login');
    }

    public function test_crear_y_actualizar_producto_por_api(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/productos', [
            'categoria_producto_id' => $this->categoria->id,
            'nombre' => 'Producto creado por API '.uniqid(),
            'unidad_control' => 'gramo',
            'precio_venta' => '25000',
        ])->assertCreated()->assertJsonPath('data.nombre', fn () => true);

        $producto = $this->producto();

        $this->putJson('/api/productos/'.$producto->id, [
            'categoria_producto_id' => $this->categoria->id,
            'nombre' => 'Nombre actualizado',
            'unidad_control' => 'gramo',
        ])->assertOk()->assertJsonPath('data.nombre', 'Nombre actualizado');
    }
}
