<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\MetodoPago;
use App\Services\CajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function __construct(private readonly CajaService $cajas) {}

    public function index(Request $request): JsonResponse
    {
        $cajaActiva = Caja::cajaAbierta();

        $historial = Caja::with(['creador', 'cerrador'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_apertura', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_apertura', '<=', $request->date('hasta')))
            ->orderByDesc('id')
            ->paginate(12);

        $movimientos = null;
        $saldosMetodo = collect();
        if ($cajaActiva) {
            $movimientos = $cajaActiva->movimientos()->with(['metodoPago', 'creador'])->orderByDesc('id')->paginate(20, ['*'], 'mov');
            $saldosMetodo = MetodoPago::orderBy('id')->get()->map(fn ($m) => [
                'metodo' => ['id' => $m->id, 'nombre' => $m->nombre],
                'esperado' => $cajaActiva->saldoEsperadoPorMetodo($m->id),
            ]);
        }

        return response()->json([
            'caja_activa' => $cajaActiva ? $this->resumen($cajaActiva) : null,
            'saldos_metodo' => $saldosMetodo->values(),
            'movimientos' => $movimientos ? [
                'data' => $movimientos->getCollection()->map(fn ($m) => [
                    'concepto' => $m->concepto, 'tipo' => $m->tipo, 'monto' => (string) $m->monto,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'metodo_nombre' => $m->metodoPago?->nombre, 'creador_nombre' => $m->creador?->name,
                ])->values(),
                'meta' => ['current_page' => $movimientos->currentPage(), 'last_page' => $movimientos->lastPage(), 'total' => $movimientos->total()],
            ] : null,
            'historial' => [
                'data' => $historial->getCollection()->map(fn ($c) => $this->resumen($c)),
                'meta' => ['current_page' => $historial->currentPage(), 'last_page' => $historial->lastPage(), 'total' => $historial->total()],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'saldo_inicial' => ['required', 'numeric', 'min:0'],
            'nota_apertura' => ['nullable', 'string', 'max:500'],
        ]);

        $caja = $this->cajas->abrir((string) $datos['saldo_inicial'], $datos['nota_apertura'] ?? null);

        return response()->json(['data' => $this->resumen($caja)], 201);
    }

    public function show(Caja $caja): JsonResponse
    {
        $caja->load(['movimientos.metodoPago', 'movimientos.creador', 'cierreDetalles.metodoPago', 'creador', 'cerrador']);
        $saldosMetodo = MetodoPago::orderBy('id')->get()->map(fn ($m) => [
            'metodo' => ['id' => $m->id, 'nombre' => $m->nombre],
            'esperado' => $caja->saldoEsperadoPorMetodo($m->id),
        ]);

        return response()->json([
            'data' => $this->resumen($caja),
            'saldos_metodo' => $saldosMetodo->values(),
            'movimientos' => $caja->movimientos->map(fn ($m) => [
                'concepto' => $m->concepto, 'tipo' => $m->tipo, 'monto' => (string) $m->monto,
                'created_at' => $m->created_at?->toIso8601String(),
                'metodo_nombre' => $m->metodoPago?->nombre,
            ])->values(),
        ]);
    }

    public function movimiento(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:ingreso,egreso'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['required', 'exists:metodo_pagos,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        $this->cajas->movimientoManual(
            $datos['tipo'], (string) $datos['monto'], (int) $datos['metodo_pago_id'],
            $datos['concepto'], $datos['descripcion'] ?? null,
        );

        return response()->json(['message' => 'Movimiento de caja registrado.'], 201);
    }

    public function cerrar(Request $request, Caja $caja): JsonResponse
    {
        $datos = $request->validate([
            'conteos' => ['required', 'array'],
            'conteos.*' => ['nullable', 'numeric', 'min:0'],
            'notas_cierre' => ['nullable', 'string', 'max:1000'],
        ]);

        $caja = $this->cajas->cerrar($caja, $datos['conteos'], $datos['notas_cierre'] ?? null, []);

        return response()->json(['data' => $this->resumen($caja)]);
    }

    public function reabrir(Request $request, Caja $caja): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:300']]);
        $caja = $this->cajas->reabrir($caja, $datos['motivo']);

        return response()->json(['data' => $this->resumen($caja)]);
    }

    private function resumen(Caja $c): array
    {
        return [
            'id' => $c->id,
            'fecha_apertura' => $c->fecha_apertura?->toIso8601String(),
            'fecha_cierre' => $c->fecha_cierre?->toIso8601String(),
            'saldo_inicial' => (string) $c->saldo_inicial,
            'total_ingresos' => (string) ($c->total_ingresos ?? '0'),
            'total_egresos' => (string) ($c->total_egresos ?? '0'),
            'saldo_final_esperado' => (string) ($c->saldo_final_esperado ?? '0'),
            'saldo_final_real' => (string) ($c->saldo_final_real ?? '0'),
            'diferencia' => (string) ($c->diferencia ?? '0'),
            'estado' => $c->estado,
        ];
    }
}
