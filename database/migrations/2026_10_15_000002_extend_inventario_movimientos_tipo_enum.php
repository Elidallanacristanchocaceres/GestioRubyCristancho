<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El kardex ya registra devoluciones de stock (p. ej. al anular un consumo),
     * pero el ENUM de 'tipo' no las contemplaba. Se amplía sin tocar los datos.
     */
    public function up(): void
    {
        if (! Schema::hasTable('inventario_movimientos') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE inventario_movimientos MODIFY tipo "
            ."ENUM('entrada','salida','ajuste','compra','venta','merma','devolucion') NOT NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventario_movimientos') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "UPDATE inventario_movimientos SET tipo = 'entrada' WHERE tipo = 'devolucion'"
        );
        DB::statement(
            "ALTER TABLE inventario_movimientos MODIFY tipo "
            ."ENUM('entrada','salida','ajuste','compra','venta','merma') NOT NULL"
        );
    }
};
