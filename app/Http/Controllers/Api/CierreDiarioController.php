<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CierreDiario;
use App\Services\CierreDiarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CierreDiarioController extends Controller
{
    public function __construct(private readonly CierreDiarioService $cierres) {}

    /**
     * Resumen vivo del día + cierre guardado (si existe).
     */
    public function index(Request $request): JsonResponse
    {
        $fecha = $request->filled('fecha')
            ? Carbon::parse($request->date('fecha'))->toDateString()
            : Carbon::today()->toDateString();

        $resumen = $this->cierres->resumen($fecha);

        $cierre = CierreDiario::with(['cerrador', 'reabridor'])
            ->whereDate('fecha', $fecha)
            ->orderByDesc('id')
            ->first();

        $historialDia = CierreDiario::with(['cerrador', 'reabridor'])
            ->whereDate('fecha', $fecha)
            ->orderByDesc('id')
            ->get()
            ->map(fn (CierreDiario $c) => $this->cierres->serializar($c))
            ->values();

        return response()->json([
            'fecha' => $fecha,
            'resumen' => $resumen,
            'cierre' => $cierre ? $this->cierres->serializar($cierre) : null,
            'historial_dia' => $historialDia,
        ]);
    }

    public function historial(Request $request): JsonResponse
    {
        $desde = $request->filled('desde')
            ? Carbon::parse($request->date('desde'))->toDateString()
            : Carbon::today()->startOfMonth()->toDateString();
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->date('hasta'))->toDateString()
            : Carbon::today()->toDateString();

        if (Carbon::parse($desde)->gt(Carbon::parse($hasta))) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return response()->json($this->cierres->historial($desde, $hasta));
    }

    public function show(CierreDiario $cierre): JsonResponse
    {
        $cierre->load(['cerrador', 'reabridor']);

        return response()->json(['data' => $this->cierres->serializar($cierre)]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $cierre = $this->cierres->cerrar($datos['fecha'], $datos['notas'] ?? null);

        return response()->json([
            'message' => 'Día cerrado correctamente. La información quedó guardada como histórico.',
            'data' => $this->cierres->serializar($cierre->load(['cerrador', 'reabridor'])),
        ], 201);
    }

    public function reabrir(Request $request, CierreDiario $cierre): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:1000'],
        ]);

        $cierre = $this->cierres->reabrir($cierre, $datos['motivo']);

        return response()->json([
            'message' => 'Cierre reabierto. El registro anterior se conserva en el historial.',
            'data' => $this->cierres->serializar($cierre->load(['cerrador', 'reabridor'])),
        ]);
    }
}
