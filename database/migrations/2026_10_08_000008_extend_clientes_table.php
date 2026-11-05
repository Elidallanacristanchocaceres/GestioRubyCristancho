<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable()->after('celular');
            }
            if (! Schema::hasColumn('clientes', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('direccion');
            }
            if (! Schema::hasColumn('clientes', 'preferencias_cabello')) {
                $table->text('preferencias_cabello')->nullable()->after('observaciones');
            }
            if (! Schema::hasColumn('clientes', 'formula_color')) {
                $table->text('formula_color')->nullable()->after('preferencias_cabello');
            }
            if (! Schema::hasColumn('clientes', 'saldo_pendiente')) {
                $table->decimal('saldo_pendiente', 14, 2)->nullable()->after('formula_color');
            }
        });
    }

    public function down(): void
    {
        // Migración aditiva: no se revierten columnas para no perder datos.
    }
};
