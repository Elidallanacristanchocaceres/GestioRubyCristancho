<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            if (! Schema::hasColumn('servicios', 'costo_mano_obra')) {
                $table->decimal('costo_mano_obra', 14, 2)->nullable()->after('precio');
            }
        });

        Schema::table('metodo_pagos', function (Blueprint $table) {
            if (! Schema::hasColumn('metodo_pagos', 'es_efectivo')) {
                $table->boolean('es_efectivo')->default(false)->after('descripcion');
            }
            if (! Schema::hasColumn('metodo_pagos', 'activo')) {
                $table->boolean('activo')->default(true)->after('es_efectivo');
            }
        });
    }

    public function down(): void
    {
        // Migración aditiva: no se revierten columnas con datos reales.
    }
};
