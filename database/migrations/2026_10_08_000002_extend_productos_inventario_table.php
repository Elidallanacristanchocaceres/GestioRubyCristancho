<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'codigo')) {
                $table->string('codigo', 60)->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('productos', 'marca')) {
                $table->string('marca', 120)->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('productos', 'unidad_control')) {
                $table->string('unidad_control', 20)->default('unidad')->after('stock');
            }
            if (! Schema::hasColumn('productos', 'contenido_envase')) {
                $table->decimal('contenido_envase', 14, 3)->nullable()->after('unidad_control');
            }
            if (! Schema::hasColumn('productos', 'costo_envase')) {
                $table->decimal('costo_envase', 14, 2)->nullable()->after('contenido_envase');
            }
            if (! Schema::hasColumn('productos', 'costo_unitario')) {
                $table->decimal('costo_unitario', 16, 6)->nullable()->after('costo_envase');
            }
            if (! Schema::hasColumn('productos', 'tipo_uso')) {
                $table->string('tipo_uso', 20)->default('ambos')->after('costo_unitario');
            }
            if (! Schema::hasColumn('productos', 'proveedor_id')) {
                $table->foreignId('proveedor_id')->nullable()->after('categoria_producto_id')
                    ->constrained('proveedores')->nullOnDelete();
            }
            if (! Schema::hasColumn('productos', 'fecha_compra')) {
                $table->date('fecha_compra')->nullable()->after('precio_venta');
            }
            if (! Schema::hasColumn('productos', 'fecha_vencimiento')) {
                $table->date('fecha_vencimiento')->nullable()->after('fecha_compra');
            }
        });

        // stock y stock_minimo pasan de entero a decimal para gramos/ml. Es una
        // conversión no destructiva: los valores existentes se conservan igual.
        // SQLite no soporta MODIFY; el esquema base ya los define como decimal.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `productos` MODIFY `stock` DECIMAL(14,3) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE `productos` MODIFY `stock_minimo` DECIMAL(14,3) NOT NULL DEFAULT 0');
        }

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('inventario_movimientos', 'unidad')) {
                $table->string('unidad', 20)->default('unidad')->after('tipo');
            }
            if (! Schema::hasColumn('inventario_movimientos', 'lote_id')) {
                $table->unsignedBigInteger('lote_id')->nullable()->after('producto_id');
            }
            if (! Schema::hasColumn('inventario_movimientos', 'costo_unitario')) {
                $table->decimal('costo_unitario', 16, 6)->nullable()->after('precio_unitario');
            }
            if (! Schema::hasColumn('inventario_movimientos', 'costo_total')) {
                $table->decimal('costo_total', 14, 2)->nullable()->after('costo_unitario');
            }
            if (! Schema::hasColumn('inventario_movimientos', 'autorizado_por')) {
                $table->unsignedBigInteger('autorizado_por')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('inventario_movimientos', 'modulo')) {
                $table->string('modulo', 40)->nullable()->after('motivo');
            }
            if (! Schema::hasColumn('inventario_movimientos', 'modulo_id')) {
                $table->unsignedBigInteger('modulo_id')->nullable()->after('modulo');
            }
            if (! Schema::hasColumn('inventario_movimientos', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `inventario_movimientos` MODIFY `cantidad` DECIMAL(14,3) NOT NULL');
            DB::statement('ALTER TABLE `inventario_movimientos` MODIFY `stock_anterior` DECIMAL(14,3) NULL');
            DB::statement('ALTER TABLE `inventario_movimientos` MODIFY `stock_nuevo` DECIMAL(14,3) NULL');
        }
    }

    public function down(): void
    {
        // Migración aditiva sobre una BD con datos reales: no se revierten
        // columnas para no perder información.
    }
};
