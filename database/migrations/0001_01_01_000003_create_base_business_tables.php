<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema base del salón.
 *
 * Históricamente estas tablas (productos, clientes, servicios, citas, caja,
 * etc.) vivían solo en la base de datos real y no estaban versionadas. Esta
 * migración las crea en instalaciones nuevas para que el sistema pueda
 * arrancar sin depender de una base externa. Es idempotente: las migraciones
 * posteriores siguen añadiendo columnas con sus propias guardas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // users gana cédula y rol (la migración original solo crea lo básico).
        if (! Schema::hasColumn('users', 'cedula')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('cedula')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->default('empleado')->after('email');
            });
        }

        if (! Schema::hasTable('categoria_productos')) {
            Schema::create('categoria_productos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('grupo', 30)->nullable()->index();
                $table->string('descripcion', 300)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('categoria_servicios')) {
            Schema::create('categoria_servicios', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('descripcion', 300)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('productos')) {
            Schema::create('productos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('categoria_producto_id')->nullable();
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->string('nombre', 200);
                $table->string('codigo', 60)->nullable();
                $table->string('marca', 120)->nullable();
                $table->string('linea', 120)->nullable();
                $table->string('tono', 60)->nullable();
                $table->text('descripcion')->nullable();
                $table->decimal('precio_costo', 14, 2)->default(0);
                $table->decimal('precio_iva', 14, 2)->default(0);
                $table->decimal('precio_venta', 14, 2)->default(0);
                $table->decimal('precio_sugerido', 14, 2)->nullable();
                $table->decimal('stock', 14, 3)->default(0);
                $table->decimal('stock_minimo', 14, 3)->default(0);
                $table->string('unidad_control', 20)->default('unidad');
                $table->decimal('contenido_envase', 14, 3)->nullable();
                $table->string('presentacion', 80)->nullable();
                $table->decimal('costo_envase', 14, 2)->nullable();
                $table->decimal('costo_unitario', 16, 6)->nullable();
                $table->string('tipo_uso', 20)->default('ambos');
                $table->date('fecha_compra')->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->boolean('activo')->default(true);
                $table->boolean('pendiente_confirmacion')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index('nombre');
                $table->index('categoria_producto_id');
            });
        }

        if (! Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 200);
                $table->string('email', 150)->nullable();
                $table->date('fecha_nacimiento')->nullable();
                $table->string('celular', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('direccion', 300)->nullable();
                $table->text('observaciones')->nullable();
                $table->text('preferencias_cabello')->nullable();
                $table->text('formula_color')->nullable();
                $table->decimal('saldo_pendiente', 14, 2)->nullable()->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('nombre');
                $table->index('celular');
            });
        }

        if (! Schema::hasTable('servicios')) {
            Schema::create('servicios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('categoria_servicio_id')->nullable();
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->unsignedInteger('duracion_minutos')->default(60);
                $table->decimal('precio', 14, 2)->default(0);
                $table->decimal('costo_mano_obra', 14, 2)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index('nombre');
                $table->index('categoria_servicio_id');
            });
        }

        if (! Schema::hasTable('estilistas')) {
            Schema::create('estilistas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 150);
                $table->string('especialidad', 150)->nullable();
                $table->text('telefono')->nullable();
                $table->date('fecha_contratacion')->nullable();
                $table->decimal('salario_base', 14, 2)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('nombre');
            });
        }

        if (! Schema::hasTable('agenda_estilistas')) {
            Schema::create('agenda_estilistas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('estilista_id');
                $table->unsignedTinyInteger('dia_semana');
                $table->time('hora_inicio');
                $table->time('hora_fin');
                $table->boolean('disponible')->default(true);
                $table->timestamps();

                $table->index(['estilista_id', 'dia_semana']);
            });
        }

        if (! Schema::hasTable('citas')) {
            Schema::create('citas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('estilista_id')->nullable();
                $table->unsignedBigInteger('servicio_id')->nullable();
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->dateTime('fecha_hora');
                $table->unsignedInteger('duracion_minutos')->default(60);
                $table->decimal('precio', 14, 2)->nullable();
                $table->decimal('anticipo', 14, 2)->nullable();
                $table->decimal('saldo_pendiente', 14, 2)->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->text('notas')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('cliente_id');
                $table->index('estilista_id');
                $table->index('fecha_hora');
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('metodo_pagos')) {
            Schema::create('metodo_pagos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 60);
                $table->string('descripcion', 200)->nullable();
                $table->boolean('es_efectivo')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique('nombre');
            });
        }

        if (! Schema::hasTable('pagos')) {
            Schema::create('pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cita_id')->nullable();
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->unsignedBigInteger('metodo_pago_id');
                $table->unsignedBigInteger('caja_movimiento_id')->nullable();
                $table->decimal('monto', 14, 2);
                $table->dateTime('fecha_hora');
                $table->string('estado', 20)->default('completado');
                $table->string('referencia', 120)->nullable();
                $table->text('notas')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('cita_id');
                $table->index('venta_id');
                $table->index('metodo_pago_id');
            });
        }

        if (! Schema::hasTable('caja')) {
            Schema::create('caja', function (Blueprint $table) {
                $table->id();
                $table->dateTime('fecha_apertura');
                $table->dateTime('fecha_cierre')->nullable();
                $table->decimal('saldo_inicial', 14, 2)->default(0);
                $table->text('nota_apertura')->nullable();
                $table->decimal('total_ingresos', 14, 2)->nullable();
                $table->decimal('total_egresos', 14, 2)->nullable();
                $table->decimal('saldo_final_esperado', 14, 2)->nullable();
                $table->decimal('saldo_final_real', 14, 2)->nullable();
                $table->decimal('diferencia', 14, 2)->nullable();
                $table->string('estado', 20)->default('abierta');
                $table->text('notas_cierre')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('estado');
            });
        }

        if (! Schema::hasTable('caja_movimientos')) {
            Schema::create('caja_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('caja_id');
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->unsignedBigInteger('pago_id')->nullable();
                $table->unsignedBigInteger('gasto_id')->nullable();
                $table->string('tipo', 20);
                $table->decimal('monto', 14, 2);
                $table->unsignedBigInteger('metodo_pago_id')->nullable();
                $table->string('concepto', 200);
                $table->text('descripcion')->nullable();
                $table->string('referencia', 120)->nullable();
                $table->string('estado', 20)->default('confirmado');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('caja_id');
                $table->index(['tipo', 'estado']);
            });
        }

        if (! Schema::hasTable('gastos')) {
            Schema::create('gastos', function (Blueprint $table) {
                $table->id();
                $table->decimal('monto', 14, 2);
                $table->unsignedBigInteger('categoria_gasto_id')->nullable();
                $table->string('estado', 20)->default('pagado');
                $table->string('descripcion', 300);
                $table->date('fecha');
                $table->unsignedBigInteger('metodo_pago_id')->nullable();
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->string('comprobante', 300)->nullable();
                $table->unsignedBigInteger('compra_id')->nullable();
                $table->unsignedBigInteger('caja_movimiento_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('fecha');
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('compras')) {
            Schema::create('compras', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->string('numero_factura', 80)->nullable();
                $table->date('fecha_compra');
                $table->date('fecha_recepcion')->nullable();
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->nullable();
                $table->decimal('descuento', 14, 2)->nullable();
                $table->decimal('total_pagado', 14, 2)->nullable();
                $table->decimal('saldo_pendiente', 14, 2)->nullable();
                $table->string('estado', 20)->default('recibida');
                $table->text('notas')->nullable();
                $table->unsignedBigInteger('gasto_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('proveedor_id');
                $table->index('fecha_compra');
            });
        }

        if (! Schema::hasTable('compra_detalles')) {
            Schema::create('compra_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compra_id');
                $table->unsignedBigInteger('producto_id');
                $table->string('unidad', 20)->default('unidad');
                $table->decimal('cantidad', 14, 3);
                $table->decimal('cantidad_envases', 14, 3)->nullable();
                $table->decimal('contenido_envase', 14, 3)->nullable();
                $table->decimal('precio_unitario', 14, 2);
                $table->decimal('descuento', 14, 2)->nullable();
                $table->decimal('subtotal', 14, 2);
                $table->timestamps();

                $table->index('compra_id');
                $table->index('producto_id');
            });
        }

        if (! Schema::hasTable('inventario_movimientos')) {
            Schema::create('inventario_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producto_id');
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->string('tipo', 20);
                $table->string('unidad', 20)->default('unidad');
                $table->decimal('cantidad', 14, 3);
                $table->decimal('stock_anterior', 14, 3)->nullable();
                $table->decimal('stock_nuevo', 14, 3)->nullable();
                $table->decimal('precio_unitario', 14, 2)->nullable();
                $table->decimal('costo_unitario', 16, 6)->nullable();
                $table->decimal('costo_total', 14, 2)->nullable();
                $table->string('referencia', 120)->nullable();
                $table->string('motivo', 300)->nullable();
                $table->string('modulo', 40)->nullable();
                $table->unsignedBigInteger('modulo_id')->nullable();
                $table->unsignedBigInteger('autorizado_por')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('producto_id');
                $table->index('tipo');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('mantenimientos')) {
            Schema::create('mantenimientos', function (Blueprint $table) {
                $table->id();
                $table->string('equipo', 150);
                $table->text('descripcion')->nullable();
                $table->date('fecha_solicitud')->nullable();
                $table->date('fecha_realizado')->nullable();
                $table->decimal('costo', 14, 2)->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('estado');
            });
        }
    }

    public function down(): void
    {
        // Esquema base con datos reales: no se elimina.
    }
};
