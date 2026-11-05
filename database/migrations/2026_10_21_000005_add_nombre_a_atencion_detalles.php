<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los servicios realizados se pueden registrar de forma manual (fuera del
 * catálogo). El nombre queda guardado en la línea para no perderlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atencion_detalles', function (Blueprint $table) {
            $table->string('nombre', 255)->nullable()->after('servicio_id');
        });
    }

    public function down(): void
    {
        Schema::table('atencion_detalles', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
};
