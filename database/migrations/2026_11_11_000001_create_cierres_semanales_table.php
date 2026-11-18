<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre semanal del salón.
 *
 * Guarda una "foto" de la semana: servicios cobrados, ventas de productos,
 * costo de los productos usados, costo de los productos vendidos, gastos,
 * base del diezmo, diezmo (10 % por defecto) y neto.
 *
 * La tabla es independiente del cierre de caja: la caja cuenta el efectivo
 * del día; este cierre calcula el resultado del negocio en la semana.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cierres_semanales')) {
            return;
        }

        Schema::create('cierres_semanales', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            $table->decimal('ingresos_servicios', 14, 2)->default(0);
            $table->decimal('ingresos_productos', 14, 2)->default(0);
            $table->decimal('costo_productos_consumidos', 14, 2)->default(0);
            $table->decimal('costo_productos_vendidos', 14, 2)->default(0);
            $table->decimal('gastos', 14, 2)->default(0);

            $table->decimal('base_diezmo', 14, 2)->default(0);
            $table->decimal('porcentaje_diezmo', 5, 2)->default(10);
            $table->decimal('diezmo', 14, 2)->default(0);
            $table->decimal('neto', 14, 2)->default(0);

            $table->json('detalle')->nullable();
            $table->text('notas')->nullable();
            $table->string('estado', 20)->default('cerrado');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fecha_inicio', 'fecha_fin']);
            $table->index('fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_semanales');
    }
};
