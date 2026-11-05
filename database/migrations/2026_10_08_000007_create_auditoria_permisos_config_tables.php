<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitácora: quién creó, modificó, anuló o eliminó y con qué motivo.
        if (! Schema::hasTable('auditorias')) {
            Schema::create('auditorias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('accion', 40);
                $table->string('modulo', 40);
                $table->string('modelo', 100);
                $table->unsignedBigInteger('modelo_id')->nullable();
                $table->json('antes')->nullable();
                $table->json('despues')->nullable();
                $table->text('motivo')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['modelo', 'modelo_id']);
                $table->index('created_at');
            });
        }

        // Permisos configurables por rol sin depender de un paquete externo.
        if (! Schema::hasTable('permisos')) {
            Schema::create('permisos', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 60)->unique();
                $table->string('nombre', 120);
                $table->string('grupo', 60)->nullable();
                $table->string('descripcion', 300)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permiso_roles')) {
            Schema::create('permiso_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
                $table->string('role', 20);
                $table->boolean('permitido')->default(true);
                $table->timestamps();

                $table->unique(['permiso_id', 'role']);
            });
        }

        // Configuración general del negocio (nombre, moneda, alertas).
        if (! Schema::hasTable('configuraciones')) {
            Schema::create('configuraciones', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 80)->unique();
                $table->text('valor')->nullable();
                $table->string('tipo', 20)->default('texto');
                $table->string('grupo', 60)->nullable();
                $table->string('etiqueta', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('permiso_roles');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('auditorias');
    }
};
