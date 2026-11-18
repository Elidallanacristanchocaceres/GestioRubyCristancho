<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('categoria_productos', 'descripcion')) {
            Schema::table('categoria_productos', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }

        if (Schema::hasColumn('categoria_servicios', 'descripcion')) {
            Schema::table('categoria_servicios', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }

        if (Schema::hasColumn('categoria_gastos', 'descripcion')) {
            Schema::table('categoria_gastos', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }
    }

    public function down(): void
    {
        // No se restauran columnas eliminadas.
    }
};
