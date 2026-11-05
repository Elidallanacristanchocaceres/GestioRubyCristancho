<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Auth;

class AuditoriaService
{
    /**
     * Registra un cambio en la bitácora. Se usa dentro de las transacciones
     * de los servicios de negocio.
     */
    public static function registrar(
        string $accion,
        string $modulo,
        string $modelo,
        ?int $modeloId = null,
        array $antes = [],
        array $despues = [],
        ?string $motivo = null
    ): Auditoria {
        return Auditoria::create([
            'user_id' => Auth::id(),
            'accion' => $accion,
            'modulo' => $modulo,
            'modelo' => $modelo,
            'modelo_id' => $modeloId,
            'antes' => $antes ?: null,
            'despues' => $despues ?: null,
            'motivo' => $motivo,
            'ip' => request()->ip(),
        ]);
    }
}
