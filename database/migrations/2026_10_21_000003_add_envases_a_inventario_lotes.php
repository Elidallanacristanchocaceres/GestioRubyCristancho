<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda cuántos envases físicos trae el lote.
 *
 * Permite diferenciar "2 envases nuevos completos" de "1 envase empezado",
 * que son unidades de medida distintas: los primeros suman existencias por
 * su contenido, el segundo queda pendiente de medición.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_lotes') && ! Schema::hasColumn('inventario_lotes', 'envases')) {
            Schema::table('inventario_lotes', function (Blueprint $table) {
                $table->decimal('envases', 12, 3)->default(0)->after('medido');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventario_lotes') && Schema::hasColumn('inventario_lotes', 'envases')) {
            Schema::table('inventario_lotes', function (Blueprint $table) {
                $table->dropColumn('envases');
            });
        }
    }
};
