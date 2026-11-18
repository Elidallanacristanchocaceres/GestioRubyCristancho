<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRecordAccess
{
    /**
     * Registra en auditoría la consulta de un registro específico (rutas GET con modelo enlazado).
     */
    public function handle(Request $request, Closure $next, string $modulo, string $accion = 'ver'): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $response->isSuccessful() || ! $request->user()) {
            return $response;
        }

        $modelo = null;
        $modeloId = null;
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                $modelo = $parameter::class;
                $modeloId = $parameter->getKey();
                break;
            }
        }

        if ($modelo === null || $modeloId === null) {
            return $response;
        }

        AuditoriaService::registrar($accion, $modulo, $modelo, (int) $modeloId, [], [], 'Consulta de registro');

        return $response;
    }
}