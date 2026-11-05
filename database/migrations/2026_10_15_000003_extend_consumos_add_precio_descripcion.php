<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consumos', 'precio_cobrado')) {
            Schema::table('consumos', function (Blueprint $table) {
                $table->decimal('precio_cobrado', 14, 2)->nullable()->after('servicio_id');
                $table->text('descripcion')->nullable()->after('observaciones');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consumos')) {
            Schema::table('consumos', function (Blueprint $table) {
                if (Schema::hasColumn('consumos', 'descripcion')) {
                    $table->dropColumn('descripcion');
                }
                if (Schema::hasColumn('consumos', 'precio_cobrado')) {
                    $table->dropColumn('precio_cobrado');
                }
            });
        }
    }
};
