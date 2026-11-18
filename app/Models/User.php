<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'cedula', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EMPLEADO = 'empleado';

    public const ROLE_INVITADO = 'invitado';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'cedula' => 'encrypted',
        ];
    }

    public function esAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function rolLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_EMPLEADO => 'Empleado',
            self::ROLE_INVITADO => 'Consultor (Contadores)',
            default => 'Consultor (Contadores)',
        };
    }

    /**
     * Permisos otorgados explícitamente a este rol.
     */
    public function permisosOtorgados(): \Illuminate\Support\Collection
    {
        return $this->permisosPorEstado(true);
    }

    public function permisosNegados(): \Illuminate\Support\Collection
    {
        return $this->permisosPorEstado(false);
    }

    private function permisosPorEstado(bool $permitido): \Illuminate\Support\Collection
    {
        return DB::table('permiso_roles')
            ->join('permisos', 'permisos.id', '=', 'permiso_roles.permiso_id')
            ->where('permiso_roles.role', $this->role)
            ->where('permiso_roles.permitido', $permitido)
            ->pluck('permisos.clave');
    }

    /**
     * El admin tiene todos los permisos; los demás roles se consultan en BD.
     */
    public function puede(string $clave): bool
    {
        if ($this->esAdmin()) {
            return true;
        }

        if ($this->permisosNegados()->contains($clave)) {
            return false;
        }

        return $this->permisosOtorgados()->contains($clave);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'created_by');
    }
}
