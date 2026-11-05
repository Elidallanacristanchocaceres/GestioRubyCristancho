<?php

namespace Database\Seeders;

use App\Models\CategoriaGasto;
use App\Models\Configuracion;
use App\Models\MetodoPago;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        // Métodos de pago requeridos por el sistema (no se duplican).
        $metodos = [
            ['nombre' => 'Efectivo', 'es_efectivo' => true, 'descripcion' => 'Pago en efectivo'],
            ['nombre' => 'Transferencia', 'es_efectivo' => false, 'descripcion' => 'Transferencia bancaria'],
            ['nombre' => 'Tarjeta', 'es_efectivo' => false, 'descripcion' => 'Tarjeta débito/crédito'],
            ['nombre' => 'Nequi', 'es_efectivo' => false, 'descripcion' => 'Billetera digital Nequi'],
            ['nombre' => 'Daviplata', 'es_efectivo' => false, 'descripcion' => 'Billetera digital Daviplata'],
        ];

        foreach ($metodos as $metodo) {
            MetodoPago::updateOrCreate(['nombre' => $metodo['nombre']], $metodo);
        }

        // Marcar el método "Efectivo" existente si no tenía la bandera.
        DB::table('metodo_pagos')->whereIn('nombre', ['efectivo', 'Efectivo'])->update(['es_efectivo' => true]);

        foreach ([
            'Arriendo', 'Servicios públicos', 'Nómina', 'Comisiones', 'Productos',
            'Mantenimiento', 'Transporte', 'Alimentación', 'Otros',
        ] as $categoria) {
            CategoriaGasto::updateOrCreate(['nombre' => $categoria], ['activo' => true]);
        }

        $configuraciones = [
            ['clave' => 'negocio_nombre', 'valor' => 'Ruby Cristancho Peluquería', 'etiqueta' => 'Nombre del negocio', 'grupo' => 'general'],
            ['clave' => 'negocio_nit', 'valor' => '', 'etiqueta' => 'NIT / documento', 'grupo' => 'general'],
            ['clave' => 'negocio_telefono', 'valor' => '', 'etiqueta' => 'Teléfono', 'grupo' => 'general'],
            ['clave' => 'negocio_direccion', 'valor' => '', 'etiqueta' => 'Dirección', 'grupo' => 'general'],
            ['clave' => 'moneda', 'valor' => 'COP', 'etiqueta' => 'Moneda', 'grupo' => 'general'],
            ['clave' => 'iva_porcentaje', 'valor' => '19', 'etiqueta' => 'IVA (%)', 'grupo' => 'general'],
            ['clave' => 'alerta_stock_bajo', 'valor' => '1', 'etiqueta' => 'Alertas de stock bajo', 'grupo' => 'alertas'],
            ['clave' => 'alerta_vencimiento_dias', 'valor' => '30', 'etiqueta' => 'Días de aviso de vencimiento', 'grupo' => 'alertas'],
        ];

        foreach ($configuraciones as $config) {
            Configuracion::updateOrCreate(['clave' => $config['clave']], $config + ['tipo' => 'texto']);
        }
    }
}
