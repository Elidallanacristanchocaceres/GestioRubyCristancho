<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cabecera: un consumo agrupa varios productos usados en una visita.
        // Las líneas viven en consumos_servicio (tabla existente, se reutiliza).
        if (! Schema::hasTable('consumos')) {
            Schema::create('consumos', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->nullable()->unique();
                $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
                $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
                $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
                $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
                $table->foreignId('estilista_id')->nullable()->constrained('estilistas')->nullOnDelete();
                $table->date('fecha');
                $table->text('observaciones')->nullable();
                $table->decimal('costo_total', 14, 2)->default(0);
                $table->string('estado', 20)->default('activo');
                $table->unsignedBigInteger('reversado_por')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('fecha');
                $table->index('estado');
            });
        }

        if (Schema::hasTable('consumos_servicio') && ! Schema::hasColumn('consumos_servicio', 'consumo_id')) {
            Schema::table('consumos_servicio', function (Blueprint $table) {
                $table->foreignId('consumo_id')->nullable()->after('id')
                    ->constrained('consumos')->cascadeOnDelete();

                // Snapshot de los lotes usados (para devolver el stock si se anula).
                $table->json('lotes_consumidos')->nullable()->after('costo_total');
            });
        }
    }

    public function down(): void
    {
        // BD con datos reales: se conservan las columnas para no perder historial.
    }
};
