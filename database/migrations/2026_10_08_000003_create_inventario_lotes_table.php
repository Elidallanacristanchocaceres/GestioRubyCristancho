<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lotes de inventario: cada compra o entrada genera un lote con su
        // costo unitario propio. El consumo usa FIFO y así el costo real del
        // gramo utilizado es trazable.
        if (! Schema::hasTable('inventario_lotes')) {
            Schema::create('inventario_lotes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
                $table->unsignedBigInteger('compra_id')->nullable();
                $table->decimal('cantidad_inicial', 14, 3);
                $table->decimal('cantidad_disponible', 14, 3);
                $table->decimal('costo_unitario', 16, 6);
                $table->decimal('costo_total', 14, 2);
                $table->date('fecha_ingreso');
                $table->date('fecha_vencimiento')->nullable();
                $table->string('estado', 20)->default('activo');
                $table->text('notas')->nullable();
                $table->timestamps();

                $table->index(['producto_id', 'estado', 'fecha_ingreso']);
                $table->index('compra_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_lotes');
    }
};
