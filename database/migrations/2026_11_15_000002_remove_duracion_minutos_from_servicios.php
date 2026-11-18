<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servicios', 'duracion_minutos')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->dropColumn('duracion_minutos');
            });
        }
    }

    public function down(): void
    {
        // No se restaura la columna eliminada.
    }
};
