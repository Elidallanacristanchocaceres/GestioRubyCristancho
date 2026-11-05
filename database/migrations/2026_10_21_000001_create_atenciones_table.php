<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro diario de clientes (atenciones del día).
 *
 * Separado del registro de productos (consumos): aquí se anota a qué cliente
 * se atendió, qué servicio(s) se le realizó y el total cobrado. No descuenta
 * inventario; es el registro comercial del salón.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('atenciones')) {
            Schema::create('atenciones', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->nullable()->unique();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('cita_id')->nullable();
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->unsignedBigInteger('estilista_id')->nullable();
                $table->date('fecha');
                $table->text('descripcion')->nullable();
                $table->text('observaciones')->nullable();
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('pagado', 14, 2)->default(0);
                $table->string('metodo_pago', 60)->nullable();
                $table->string('estado', 20)->default('activo');
                $table->unsignedBigInteger('reversado_por')->nullable();
                $table->timestamp('reversado_en')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('fecha');
                $table->index('cliente_id');
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('atencion_detalles')) {
            Schema::create('atencion_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('atencion_id');
                $table->unsignedBigInteger('servicio_id')->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->decimal('cantidad', 10, 3)->default(1);
                $table->decimal('precio_unitario', 14, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->unsignedBigInteger('estilista_id')->nullable();
                $table->timestamps();

                $table->index('atencion_id');
                $table->index('servicio_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('atencion_detalles');
        Schema::dropIfExists('atenciones');
    }
};
