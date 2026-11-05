<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Receta técnica: qué productos y cuánto consume cada servicio.
        if (! Schema::hasTable('servicio_productos')) {
            Schema::create('servicio_productos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->decimal('cantidad_sugerida', 14, 3);
                $table->decimal('costo_estimado', 14, 2)->nullable();
                $table->text('notas')->nullable();
                $table->timestamps();

                $table->unique(['servicio_id', 'producto_id']);
            });
        }

        // Consumo real por ejecución de servicio (ligado a cita y venta).
        if (! Schema::hasTable('consumos_servicio')) {
            Schema::create('consumos_servicio', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cita_id')->nullable();
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->foreignId('producto_id')->constrained('productos');
                $table->foreignId('estilista_id')->nullable()->constrained('estilistas')->nullOnDelete();
                $table->decimal('cantidad', 14, 3);
                $table->string('unidad', 20);
                $table->decimal('costo_unitario', 16, 6);
                $table->decimal('costo_total', 14, 2);
                $table->string('estado', 20)->default('activo');
                $table->unsignedBigInteger('reversado_por')->nullable();
                $table->text('notas')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('cita_id');
                $table->index('venta_id');
                $table->index('producto_id');
                $table->index('estado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_servicio');
        Schema::dropIfExists('servicio_productos');
    }
};
