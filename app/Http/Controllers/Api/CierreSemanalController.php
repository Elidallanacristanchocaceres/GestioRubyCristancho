<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CierreSemanal;
use App\Services\CierreSemanalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CierreSemanalController extends Controller
{
    public function __construct(private readonly CierreSemanalService $cierres) {}

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'semana' => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $semana = $this->cierres->semana($datos['semana'] ?? null);
        } catch (Throwable) {
            $semana = $this->cierres->semana();
        }

        $resumen = $this->cierres->resumen($semana['inicio'], $semana['fin']);

        $cierre = CierreSemanal::with('creador')
            ->where('fecha_inicio', $semana['inicio'])
            ->where('fecha_fin', $semana['fin'])
            ->first();

        $historial = CierreSemanal::with('creador')
            ->orderByDesc('fecha_inicio')
            ->limit(24)
            ->get()
            ->map(fn (CierreSemanal $c) => $this->serializar($c))
            ->values();

        return response()->json([
            'semana' => $semana,
            'resumen' => $resumen,
            'cierre' => $cierre ? $this->serializar($cierre) : null,
            'historial' => $historial,
            'semanas' => $this->cierres->semanas(12),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'semana' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $cierre = $this->cierres->cerrar($datos);
        $cierre->load('creador');

        return response()->json(['cierre' => $this->serializar($cierre)], 201);
    }

    public function show(CierreSemanal $cierre): JsonResponse
    {
        $cierre->load('creador');

        return response()->json(['cierre' => $this->serializar($cierre)]);
    }

    public function destroy(CierreSemanal $cierre): JsonResponse
    {
        $this->cierres->eliminar($cierre);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(CierreSemanal $cierre): array
    {
        return [
            'id' => $cierre->id,
            'fecha_inicio' => $cierre->fecha_inicio->toDateString(),
            'fecha_fin' => $cierre->fecha_fin->toDateString(),
            'etiqueta' => $cierre->etiqueta(),
            'ingresos_servicios' => (string) $cierre->ingresos_servicios,
            'ingresos_productos' => (string) $cierre->ingresos_productos,
            'costo_productos_consumidos' => (string) $cierre->costo_productos_consumidos,
            'costo_productos_vendidos' => (string) $cierre->costo_productos_vendidos,
            'gastos' => (string) $cierre->gastos,
            'base_diezmo' => (string) $cierre->base_diezmo,
            'porcentaje_diezmo' => (string) $cierre->porcentaje_diezmo,
            'diezmo' => (string) $cierre->diezmo,
            'neto' => (string) $cierre->neto,
            'detalle' => $cierre->detalle ?? [],
            'notas' => $cierre->notas,
            'estado' => $cierre->estado,
            'creador_nombre' => $cierre->creador?->name,
            'created_at' => $cierre->created_at?->toDateTimeString(),
        ];
    }
}
