<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = [
        'clave',
        'nombre',
        'grupo',
        'descripcion',
    ];

    public function permitidoPara(string $role): bool
    {
        $pivote = DB::table('permiso_roles')
            ->where('permiso_id', $this->id)
            ->where('role', $role)
            ->first();

        return (bool) ($pivote->permitido ?? false);
    }
}
