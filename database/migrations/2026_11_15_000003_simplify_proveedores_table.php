<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedores')) {
            if (! Schema::hasColumn('proveedores', 'nombre_marca')) {
                Schema::table('proveedores', function (Blueprint $table) {
                    $table->string('nombre_marca', 200)->nullable()->after('id');
                });
            }

            $drop = array_values(array_filter([
                Schema::hasColumn('proveedores', 'nit') ? 'nit' : null,
                Schema::hasColumn('proveedores', 'contacto') ? 'contacto' : null,
                Schema::hasColumn('proveedores', 'whatsapp') ? 'whatsapp' : null,
                Schema::hasColumn('proveedores', 'email') ? 'email' : null,
                Schema::hasColumn('proveedores', 'direccion') ? 'direccion' : null,
                Schema::hasColumn('proveedores', 'observaciones') ? 'observaciones' : null,
            ]));

            if ($drop !== []) {
                Schema::table('proveedores', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }
    }

    public function down(): void
    {
        // No se restauran columnas eliminadas.
    }
};
