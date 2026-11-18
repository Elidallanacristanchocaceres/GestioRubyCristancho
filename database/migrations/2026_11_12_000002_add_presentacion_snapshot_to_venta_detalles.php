<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conserva la presentación y el contenido del producto en el momento de la
 * venta. La ficha del producto puede cambiar después; el histórico de la venta
 * debe seguir mostrando lo que realmente se entregó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venta_detalles', 'presentacion')) {
            Schema::table('venta_detalles', function (Blueprint $table) {
                $table->string('presentacion', 80)->nullable()->after('unidad');
            });
        }

        if (! Schema::hasColumn('venta_detalles', 'contenido_envase')) {
            Schema::table('venta_detalles', function (Blueprint $table) {
                $table->decimal('contenido_envase', 14, 3)->nullable()->after('presentacion');
            });
        }
    }

    public function down(): void
    {
        // No se eliminan columnas: se conserva el histórico de ventas.
    }
};
