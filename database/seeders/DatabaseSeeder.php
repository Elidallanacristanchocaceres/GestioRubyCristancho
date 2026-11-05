<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Datos de configuración y permisos (idempotentes, no destruyen datos).
        $this->call([
            ConfiguracionSeeder::class,
            PermisoSeeder::class,
        ]);

        // Usuario administrador de acceso inicial. Cambie la contraseña al
        // ingresar por primera vez.
        $admin = User::firstOrNew(['cedula' => '1004844018']);
        $admin->name = $admin->name ?: 'Administrador';
        $admin->role = 'admin';
        $admin->email = $admin->email ?: 'admin@rubycristancho.local';
        $admin->password = Hash::make('Ruby2026*');
        $admin->save();

        $this->command?->info('Admin: admin@rubycristancho.local / Ruby2026*');
    }
}
