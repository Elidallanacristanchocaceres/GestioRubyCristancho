<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Control financiero diario.
 *
 * - Separa los gastos del salón y de la casa sin alterar los gastos históricos:
 *   los registros existentes quedan como "salón".
 * - Crea el cierre diario con una foto JSON de todos los movimientos y los
 *   totales del día, para conservar los valores aunque luego cambien precios.
 *
 * Las guardas permiten ejecutar la migración sobre la base real sin recrear
 * tablas ni perder información.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categoria_gastos', 'ambito')) {
            Schema::table('categoria_gastos', function (Blueprint $table) {
                $table->string('ambito', 20)->default('salon')->after('nombre')->index();
            });
        }

        if (! Schema::hasColumn('gastos', 'ambito')) {
            Schema::table('gastos', function (Blueprint $table) {
                $table->string('ambito', 20)->default('salon')->after('categoria_gasto_id')->index();
            });
        }

        if (! Schema::hasTable('cierres_diarios')) {
            Schema::create('cierres_diarios', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->index();
                $table->string('estado', 20)->default('cerrado')->index();

                $table->decimal('total_servicios', 14, 2)->default(0);
                $table->decimal('total_productos_vendidos', 14, 2)->default(0);
                $table->decimal('total_productos_utilizados', 14, 2)->default(0);
                $table->decimal('total_gastos_salon', 14, 2)->default(0);
                $table->decimal('total_gastos_casa', 14, 2)->default(0);
                $table->decimal('total_ingresos', 14, 2)->default(0);
                $table->decimal('total_descuentos', 14, 2)->default(0);
                $table->decimal('resultado', 14, 2)->default(0);

                // Dato informativo: no se resta otra vez en la fórmula diaria.
                $table->decimal('costo_productos_vendidos', 14, 2)->default(0);

                $table->json('detalle')->nullable();
                $table->text('notas')->nullable();

                $table->unsignedBigInteger('cerrado_por')->nullable()->index();
                $table->dateTime('cerrado_en')->nullable();
                $table->unsignedBigInteger('reabierto_por')->nullable()->index();
                $table->dateTime('reabierto_en')->nullable();
                $table->text('motivo_reapertura')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
            });
        }

        // En instalaciones nuevas la FK se agrega solo si las columnas existen
        // y el motor lo permite; la base real puede tener usuarios históricos.
        if (Schema::hasTable('cierres_diarios') && DB::getDriverName() === 'mysql') {
            $this->agregarIndice('cierres_diarios', 'cierres_diarios_fecha_estado_index', ['fecha', 'estado']);
        }
    }

    public function down(): void
    {
        // No se eliminan columnas ni la tabla: la base real conserva historial.
    }

    /**
     * @param  array<int, string>  $columnas
     */
    private function agregarIndice(string $tabla, string $nombre, array $columnas): void
    {
        $existe = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$tabla, $nombre]
        );

        if ($existe === []) {
            Schema::table($tabla, function (Blueprint $table) use ($nombre, $columnas) {
                $table->index($columnas, $nombre);
            });
        }
    }
};
