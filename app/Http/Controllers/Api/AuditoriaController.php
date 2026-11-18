<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $registros = Auditoria::with('usuario')
            ->when($request->filled('modulo'), fn ($q) => $q->where('modulo', $request->str('modulo')))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->str('accion')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')))
            ->orderByDesc('id')
            ->paginate(25);

        $modulos = Auditoria::query()->distinct()->pluck('modulo')->filter()->sort()->values()->all();

        return response()->json([
            'data' => $registros->getCollection()->map(fn ($r) => [
                'id' => $r->id,
                'fecha' => $r->created_at?->toIso8601String(),
                'usuario_nombre' => $r->usuario?->name ?? 'Sistema',
                'accion' => $r->accion,
                'accion_label' => $r->etiquetaAccion(),
                'modulo' => $r->modulo,
                'modulo_label' => str($r->modulo ?? '')->replace('_', ' ')->ucfirst()->toString(),
                'modelo' => class_basename((string) $r->modelo),
                'modelo_id' => $r->modelo_id,
                'motivo' => $r->motivo,
            ]),
            'meta' => [
                'current_page' => $registros->currentPage(),
                'last_page' => $registros->lastPage(),
                'total' => $registros->total(),
            ],
            'modulos' => $modulos,
            'acciones' => ['crear', 'actualizar', 'eliminar', 'anular', 'pago', 'apertura', 'cierre', 'ajuste', 'consumo', 'ver'],
        ]);
    }
}
