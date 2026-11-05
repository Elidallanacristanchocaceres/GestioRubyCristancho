<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentación comercial del producto (ej. "1000 ml", "12 x 10 ml").
 *
 * Es independiente del contenido_envase: permite describir ENVASES
 * (bolsas, cajas) sin forzar la unidad de control, y mostrar la
 * presentación tal como la maneja el salón en el listado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('productos') && ! Schema::hasColumn('productos', 'presentacion')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->string('presentacion', 80)->nullable()->after('contenido_envase');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'presentacion')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('presentacion');
            });
        }
    }
};
