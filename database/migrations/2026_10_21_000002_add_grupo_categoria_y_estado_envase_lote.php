<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organización de productos e inventario:
 *
 *  - categoria_productos.grupo: agrupa las categorías por tipo de producto
 *    (tintes, activadores, decolorantes, shampoos, tratamientos, ampollas,
 *    otros) para poder filtrar el inventario sin mezclar líneas ni tonos.
 *
 *  - inventario_lotes.estado_envase y .medido: distinguen envases nuevos de
 *    envases empezados. Un envase iniciado cuyo contenido no se ha medido
 *    queda registrado pero NO suma existencias hasta que se mida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categoria_productos') && ! Schema::hasColumn('categoria_productos', 'grupo')) {
            Schema::table('categoria_productos', function (Blueprint $table) {
                $table->string('grupo', 30)->nullable()->after('nombre')->default(null)->index();
            });
        }

        if (Schema::hasTable('inventario_lotes')) {
            if (! Schema::hasColumn('inventario_lotes', 'estado_envase')) {
                Schema::table('inventario_lotes', function (Blueprint $table) {
                    $table->string('estado_envase', 20)->nullable()->after('estado')->default('nuevo');
                });
            }

            if (! Schema::hasColumn('inventario_lotes', 'medido')) {
                Schema::table('inventario_lotes', function (Blueprint $table) {
                    $table->boolean('medido')->default(true)->after('estado_envase');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventario_lotes')) {
            Schema::table('inventario_lotes', function (Blueprint $table) {
                if (Schema::hasColumn('inventario_lotes', 'medido')) {
                    $table->dropColumn('medido');
                }
                if (Schema::hasColumn('inventario_lotes', 'estado_envase')) {
                    $table->dropColumn('estado_envase');
                }
            });
        }

        if (Schema::hasTable('categoria_productos') && Schema::hasColumn('categoria_productos', 'grupo')) {
            Schema::table('categoria_productos', function (Blueprint $table) {
                $table->dropColumn('grupo');
            });
        }
    }
};
