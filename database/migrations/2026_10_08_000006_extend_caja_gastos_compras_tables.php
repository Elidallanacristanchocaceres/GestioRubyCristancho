<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('caja_movimientos', 'metodo_pago_id')) {
                $table->foreignId('metodo_pago_id')->nullable()->after('monto')
                    ->constrained('metodo_pagos')->nullOnDelete();
            }
            if (! Schema::hasColumn('caja_movimientos', 'estado')) {
                $table->string('estado', 20)->default('confirmado')->after('referencia');
            }
            if (! Schema::hasColumn('caja_movimientos', 'venta_id')) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('caja_id');
            }
            if (! Schema::hasColumn('caja_movimientos', 'pago_id')) {
                $table->unsignedBigInteger('pago_id')->nullable()->after('venta_id');
            }
            if (! Schema::hasColumn('caja_movimientos', 'gasto_id')) {
                $table->unsignedBigInteger('gasto_id')->nullable()->after('pago_id');
            }
        });

        DB::statement('ALTER TABLE `caja_movimientos` MODIFY `monto` DECIMAL(14,2) NOT NULL');

        // Conteo por medio de pago al cerrar la caja.
        if (! Schema::hasTable('caja_cierre_detalles')) {
            Schema::create('caja_cierre_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('caja_id')->constrained('caja')->cascadeOnDelete();
                $table->foreignId('metodo_pago_id')->constrained('metodo_pagos');
                $table->decimal('saldo_esperado', 14, 2)->default(0);
                $table->decimal('valor_contado', 14, 2)->default(0);
                $table->decimal('diferencia', 14, 2)->default(0);
                $table->text('observaciones')->nullable();
                $table->timestamps();

                $table->unique(['caja_id', 'metodo_pago_id']);
            });
        }

        Schema::table('caja', function (Blueprint $table) {
            if (! Schema::hasColumn('caja', 'nota_apertura')) {
                $table->text('nota_apertura')->nullable()->after('saldo_inicial');
            }
            if (! Schema::hasColumn('caja', 'total_ingresos')) {
                $table->decimal('total_ingresos', 14, 2)->nullable()->after('nota_apertura');
            }
            if (! Schema::hasColumn('caja', 'total_egresos')) {
                $table->decimal('total_egresos', 14, 2)->nullable()->after('total_ingresos');
            }
        });

        DB::statement('ALTER TABLE `caja` MODIFY `saldo_inicial` DECIMAL(14,2) NOT NULL DEFAULT 0');

        // Gastos ganan categoría configurable, medio de pago y estado.
        Schema::table('gastos', function (Blueprint $table) {
            if (! Schema::hasColumn('gastos', 'categoria_gasto_id')) {
                $table->foreignId('categoria_gasto_id')->nullable()->after('monto')
                    ->constrained('categoria_gastos')->nullOnDelete();
            }
            if (! Schema::hasColumn('gastos', 'metodo_pago_id')) {
                $table->foreignId('metodo_pago_id')->nullable()->after('fecha')
                    ->constrained('metodo_pagos')->nullOnDelete();
            }
            if (! Schema::hasColumn('gastos', 'proveedor_id')) {
                $table->foreignId('proveedor_id')->nullable()->after('metodo_pago_id')
                    ->constrained('proveedores')->nullOnDelete();
            }
            if (! Schema::hasColumn('gastos', 'estado')) {
                $table->string('estado', 20)->default('pagado')->after('descripcion');
            }
            if (! Schema::hasColumn('gastos', 'comprobante')) {
                $table->string('comprobante', 300)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('gastos', 'compra_id')) {
                $table->unsignedBigInteger('compra_id')->nullable()->after('comprobante');
            }
            if (! Schema::hasColumn('gastos', 'caja_movimiento_id')) {
                $table->unsignedBigInteger('caja_movimiento_id')->nullable()->after('compra_id');
            }
        });

        DB::statement('ALTER TABLE `gastos` MODIFY `monto` DECIMAL(14,2) NOT NULL');

        // Compras ganan proveedor, factura y control de pagos.
        Schema::table('compras', function (Blueprint $table) {
            if (! Schema::hasColumn('compras', 'proveedor_id')) {
                $table->foreignId('proveedor_id')->nullable()->after('id')
                    ->constrained('proveedores')->nullOnDelete();
            }
            if (! Schema::hasColumn('compras', 'numero_factura')) {
                $table->string('numero_factura', 80)->nullable()->after('proveedor_id');
            }
            if (! Schema::hasColumn('compras', 'fecha_recepcion')) {
                $table->date('fecha_recepcion')->nullable()->after('fecha_compra');
            }
            if (! Schema::hasColumn('compras', 'subtotal')) {
                $table->decimal('subtotal', 14, 2)->nullable()->after('total');
            }
            if (! Schema::hasColumn('compras', 'descuento')) {
                $table->decimal('descuento', 14, 2)->nullable()->after('subtotal');
            }
            if (! Schema::hasColumn('compras', 'total_pagado')) {
                $table->decimal('total_pagado', 14, 2)->nullable()->after('descuento');
            }
            if (! Schema::hasColumn('compras', 'saldo_pendiente')) {
                $table->decimal('saldo_pendiente', 14, 2)->nullable()->after('total_pagado');
            }
            if (! Schema::hasColumn('compras', 'gasto_id')) {
                $table->unsignedBigInteger('gasto_id')->nullable()->after('notas');
            }
        });

        DB::statement('ALTER TABLE `compras` MODIFY `total` DECIMAL(14,2) NOT NULL DEFAULT 0');

        Schema::table('compra_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('compra_detalles', 'unidad')) {
                $table->string('unidad', 20)->default('unidad')->after('producto_id');
            }
            if (! Schema::hasColumn('compra_detalles', 'cantidad_envases')) {
                $table->decimal('cantidad_envases', 14, 3)->nullable()->after('cantidad');
            }
            if (! Schema::hasColumn('compra_detalles', 'contenido_envase')) {
                $table->decimal('contenido_envase', 14, 3)->nullable()->after('cantidad_envases');
            }
            if (! Schema::hasColumn('compra_detalles', 'descuento')) {
                $table->decimal('descuento', 14, 2)->nullable()->after('precio_unitario');
            }
        });

        DB::statement('ALTER TABLE `compra_detalles` MODIFY `cantidad` DECIMAL(14,3) NOT NULL');
        DB::statement('ALTER TABLE `compra_detalles` MODIFY `subtotal` DECIMAL(14,2) NOT NULL');

        // Compras a proveedor en cuotas / abonos.
        if (! Schema::hasTable('compra_pagos')) {
            Schema::create('compra_pagos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
                $table->foreignId('metodo_pago_id')->constrained('metodo_pagos');
                $table->decimal('monto', 14, 2);
                $table->string('tipo', 20)->default('pago');
                $table->dateTime('fecha_hora');
                $table->string('referencia', 120)->nullable();
                $table->unsignedBigInteger('caja_movimiento_id')->nullable();
                $table->text('notas')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('compra_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_pagos');
        Schema::dropIfExists('caja_cierre_detalles');
    }
};
