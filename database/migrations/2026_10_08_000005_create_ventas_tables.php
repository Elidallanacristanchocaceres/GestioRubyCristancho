<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ventas')) {
            Schema::create('ventas', function (Blueprint $table) {
                $table->id();
                $table->string('numero', 30)->unique();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('cita_id')->nullable();
                $table->unsignedBigInteger('estilista_id')->nullable();
                $table->date('fecha');
                $table->dateTime('fecha_hora');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('descuento', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('total_pagado', 14, 2)->default(0);
                $table->decimal('saldo_pendiente', 14, 2)->default(0);
                $table->string('estado', 20)->default('abierta');
                $table->string('tipo', 20)->default('servicio');
                $table->text('notas')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('cliente_id');
                $table->index('cita_id');
                $table->index('fecha');
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('venta_detalles')) {
            Schema::create('venta_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
                $table->string('tipo', 20)->default('servicio');
                $table->unsignedBigInteger('servicio_id')->nullable();
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('descripcion', 250);
                $table->decimal('cantidad', 14, 3)->default(1);
                $table->string('unidad', 20)->default('unidad');
                $table->decimal('precio_unitario', 14, 2);
                $table->decimal('descuento', 14, 2)->default(0);
                $table->decimal('subtotal', 14, 2);
                $table->decimal('costo_unitario', 16, 6)->nullable();
                $table->decimal('costo_total', 14, 2)->nullable();
                $table->timestamps();

                $table->index('venta_id');
                $table->index('servicio_id');
                $table->index('producto_id');
            });
        }

        // Pagos divididos: cada fila es un medio de pago distinto de una venta.
        if (! Schema::hasTable('venta_pagos')) {
            Schema::create('venta_pagos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
                $table->foreignId('metodo_pago_id')->constrained('metodo_pagos');
                $table->decimal('monto', 14, 2);
                $table->string('tipo', 20)->default('pago');
                $table->dateTime('fecha_hora');
                $table->string('referencia', 120)->nullable();
                $table->string('estado', 20)->default('completado');
                $table->unsignedBigInteger('caja_movimiento_id')->nullable();
                $table->text('notas')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('venta_id');
                $table->index('metodo_pago_id');
                $table->index('fecha_hora');
                $table->index('estado');
            });
        }

        // Cita gana campos de valor, anticipo y estado de cobro.
        Schema::table('citas', function (Blueprint $table) {
            if (! Schema::hasColumn('citas', 'precio')) {
                $table->decimal('precio', 14, 2)->nullable()->after('duracion_minutos');
            }
            if (! Schema::hasColumn('citas', 'anticipo')) {
                $table->decimal('anticipo', 14, 2)->nullable()->after('precio');
            }
            if (! Schema::hasColumn('citas', 'saldo_pendiente')) {
                $table->decimal('saldo_pendiente', 14, 2)->nullable()->after('anticipo');
            }
            if (! Schema::hasColumn('citas', 'venta_id')) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('servicio_id');
            }
        });

        // Pagos ahora pueden pertenecer a una venta o a una cita histórica.
        Schema::table('pagos', function (Blueprint $table) {
            if (! Schema::hasColumn('pagos', 'venta_id')) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('cita_id');
            }
            if (! Schema::hasColumn('pagos', 'caja_movimiento_id')) {
                $table->unsignedBigInteger('caja_movimiento_id')->nullable()->after('metodo_pago_id');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `pagos` MODIFY `cita_id` BIGINT(20) UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};
