<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $drop = array_values(array_filter([
            Schema::hasColumn('productos', 'tipo_uso') ? 'tipo_uso' : null,
            Schema::hasColumn('productos', 'linea') ? 'linea' : null,
            Schema::hasColumn('productos', 'codigo') ? 'codigo' : null,
        ]));

        if ($drop !== []) {
            Schema::table('productos', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }

    public function down(): void
    {
        // No se restauran columnas eliminadas.
    }
};
