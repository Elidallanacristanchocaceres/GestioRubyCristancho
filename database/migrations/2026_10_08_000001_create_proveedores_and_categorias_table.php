<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proveedores')) {
            Schema::create('proveedores', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 200);
                $table->string('nit', 30)->nullable();
                $table->string('contacto', 200)->nullable();
                $table->text('telefono')->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('direccion', 300)->nullable();
                $table->text('observaciones')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('nombre');
            });
        }

        if (! Schema::hasTable('categoria_gastos')) {
            Schema::create('categoria_gastos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('descripcion', 300)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique('nombre');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_gastos');
        Schema::dropIfExists('proveedores');
    }
};
