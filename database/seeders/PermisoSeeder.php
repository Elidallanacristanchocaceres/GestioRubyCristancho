<?php

namespace Database\Seeders;

use App\Models\Permiso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermisoSeeder extends Seeder
{
    /**
     * Permisos configurables del sistema. El admin siempre tiene todo;
     * estos se usan para los roles empleado y consultor (rol interno: invitado).
     */
    public const PERMISOS = [
        ['clave' => 'dashboard.ver', 'nombre' => 'Ver panel principal', 'grupo' => 'Panel'],
        ['clave' => 'caja.ver', 'nombre' => 'Ver caja', 'grupo' => 'Caja'],
        ['clave' => 'caja.operar', 'nombre' => 'Abrir, mover y cerrar caja', 'grupo' => 'Caja'],
        ['clave' => 'inventario.ver', 'nombre' => 'Ver inventario', 'grupo' => 'Inventario'],
        ['clave' => 'inventario.operar', 'nombre' => 'Ajustar inventario y compras', 'grupo' => 'Inventario'],
        ['clave' => 'clientes.ver', 'nombre' => 'Ver clientes', 'grupo' => 'Clientes'],
        ['clave' => 'clientes.operar', 'nombre' => 'Crear y editar clientes', 'grupo' => 'Clientes'],
        ['clave' => 'citas.ver', 'nombre' => 'Ver citas', 'grupo' => 'Citas'],
        ['clave' => 'citas.operar', 'nombre' => 'Crear y editar citas', 'grupo' => 'Citas'],
        ['clave' => 'servicios.ver', 'nombre' => 'Ver servicios', 'grupo' => 'Servicios'],
        ['clave' => 'servicios.operar', 'nombre' => 'Editar servicios y recetas', 'grupo' => 'Servicios'],
        ['clave' => 'ventas.ver', 'nombre' => 'Ver ventas', 'grupo' => 'Ventas'],
        ['clave' => 'ventas.operar', 'nombre' => 'Registrar ventas y pagos', 'grupo' => 'Ventas'],
        ['clave' => 'gastos.ver', 'nombre' => 'Ver gastos', 'grupo' => 'Gastos'],
        ['clave' => 'gastos.operar', 'nombre' => 'Registrar y editar gastos', 'grupo' => 'Gastos'],
        ['clave' => 'proveedores.ver', 'nombre' => 'Ver proveedores', 'grupo' => 'Proveedores'],
        ['clave' => 'proveedores.operar', 'nombre' => 'Editar proveedores', 'grupo' => 'Proveedores'],
        ['clave' => 'compras.ver', 'nombre' => 'Ver compras', 'grupo' => 'Compras'],
        ['clave' => 'compras.operar', 'nombre' => 'Registrar compras', 'grupo' => 'Compras'],
        ['clave' => 'reportes.ver', 'nombre' => 'Ver reportes', 'grupo' => 'Reportes'],
        ['clave' => 'usuarios.gestionar', 'nombre' => 'Gestionar usuarios', 'grupo' => 'Usuarios'],
        ['clave' => 'auditoria.ver', 'nombre' => 'Ver auditoría', 'grupo' => 'Auditoría'],
        ['clave' => 'configuracion.gestionar', 'nombre' => 'Gestionar configuración', 'grupo' => 'Configuración'],
    ];

    /**
     * Permisos por defecto para el rol empleado.
     */
    public const EMPLEADO = [
        'dashboard.ver', 'caja.ver', 'caja.operar', 'inventario.ver', 'inventario.operar',
        'clientes.ver', 'clientes.operar', 'citas.ver', 'citas.operar', 'servicios.ver',
        'ventas.ver', 'ventas.operar', 'gastos.ver', 'gastos.operar',
        'proveedores.ver', 'compras.ver', 'compras.operar', 'reportes.ver',
    ];

    /**
     * Permisos por defecto para el rol consultor/contador (rol interno: invitado).
     * Enfoque de solo consulta sobre información operativa y financiera.
     */
    public const CONSULTOR = [
        'dashboard.ver', 'caja.ver', 'inventario.ver', 'clientes.ver', 'citas.ver',
        'servicios.ver', 'ventas.ver', 'gastos.ver', 'proveedores.ver', 'compras.ver',
        'reportes.ver', 'auditoria.ver',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permiso::updateOrCreate(['clave' => $permiso['clave']], $permiso);
        }

        // Asignar permisos por defecto a empleado y consultor (rol interno: invitado).
        foreach (Permiso::all() as $permiso) {
            DB::table('permiso_roles')->updateOrInsert(
                ['permiso_id' => $permiso->id, 'role' => 'empleado'],
                ['permitido' => in_array($permiso->clave, self::EMPLEADO, true), 'created_at' => now(), 'updated_at' => now()]
            );

            DB::table('permiso_roles')->updateOrInsert(
                ['permiso_id' => $permiso->id, 'role' => 'invitado'],
                ['permitido' => in_array($permiso->clave, self::CONSULTOR, true), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
