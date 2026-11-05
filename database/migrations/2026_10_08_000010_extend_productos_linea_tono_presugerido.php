<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'linea')) {
                $table->string('linea', 120)->nullable()->after('marca');
            }
            if (! Schema::hasColumn('productos', 'tono')) {
                $table->string('tono', 60)->nullable()->after('codigo');
            }
            if (! Schema::hasColumn('productos', 'precio_sugerido')) {
                $table->decimal('precio_sugerido', 14, 2)->nullable()->after('precio_venta');
            }
            if (! Schema::hasColumn('productos', 'pendiente_confirmacion')) {
                $table->boolean('pendiente_confirmacion')->default(false)->after('activo');
            }
        });
    }

    public function down(): void
    {
        // Migración aditiva sobre una BD con datos reales: no se revierten
        // columnas para no perder información.
    }
};