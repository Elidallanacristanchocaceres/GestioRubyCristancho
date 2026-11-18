<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaGasto;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\Proveedor;
use App\Services\GastoService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function __construct(private readonly GastoService $gastos) {}

    public function index(Request $request): JsonResponse
    {
        $gastos = Gasto::with(['categoria', 'metodoPago', 'proveedor', 'creador'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_gasto_id', $request->integer('categoria')))
            ->when($request->filled('ambito'), fn ($q) => $q->where('ambito', $request->str('ambito')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')))
            ->when($request->filled('q'), fn ($q) => $q->where('descripcion', 'like', '%'.$request->str('q').'%'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15);

        $pagina = $gastos->getCollection();
        $totalPagina = Money::cero();
        $totalSalon = Money::cero();
        $totalCasa = Money::cero();

        foreach ($pagina as $g) {
            $totalPagina = Money::add($totalPagina, (string) $g->monto);
            if ($g->ambito === Gasto::AMBITO_CASA) {
                $totalCasa = Money::add($totalCasa, (string) $g->monto);
            } else {
                $totalSalon = Money::add($totalSalon, (string) $g->monto);
            }
        }

        return response()->json([
            'data' => $pagina->map(fn ($g) => $this->resumen($g)),
            'meta' => ['current_page' => $gastos->currentPage(), 'last_page' => $gastos->lastPage(), 'total' => $gastos->total()],
            'total_pagina' => $totalPagina,
            'total_salon' => $totalSalon,
            'total_casa' => $totalCasa,
            'categorias' => CategoriaGasto::orderBy('nombre')->get(['id', 'nombre', 'ambito']),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $gasto = $this->gastos->registrar($this->validar($request));
        $gasto->load(['categoria', 'metodoPago', 'proveedor', 'creador']);

        return response()->json(['data' => $this->resumen($gasto, true)], 201);
    }

    public function show(Gasto $gasto): JsonResponse
    {
        $gasto->load(['categoria', 'metodoPago', 'proveedor', 'creador', 'compra']);

        return response()->json([
            'data' => $this->resumen($gasto, true),
            'categorias' => CategoriaGasto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'ambito']),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, Gasto $gasto): JsonResponse
    {
        $gasto = $this->gastos->actualizar($gasto, $this->validar($request));
        $gasto->load(['categoria', 'metodoPago', 'proveedor', 'creador']);

        return response()->json(['data' => $this->resumen($gasto, true)]);
    }

    public function destroy(Request $request, Gasto $gasto): JsonResponse
    {
        $motivo = $request->filled('motivo') ? $request->str('motivo')->toString() : null;
        $this->gastos->eliminar($gasto, $motivo);

        return response()->json(['message' => 'Gasto eliminado correctamente.']);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'categoria_gasto_id' => ['nullable', 'exists:categoria_gastos,id'],
            'ambito' => ['nullable', 'in:salon,casa'],
            'descripcion' => ['required', 'string', 'max:500'],
            'fecha' => ['required', 'date'],
            'metodo_pago_id' => ['nullable', 'exists:metodo_pagos,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'estado' => ['required', 'in:pagado,pendiente'],
            'comprobante' => ['nullable', 'string', 'max:300'],
            'afectar_caja' => ['boolean'],
        ], [], ['monto' => 'valor', 'categoria_gasto_id' => 'categoría', 'metodo_pago_id' => 'medio de pago'])
            + ['afectar_caja' => $request->boolean('afectar_caja', true)];
    }

    private function resumen(Gasto $g, bool $detalle = false): array
    {
        $base = [
            'id' => $g->id,
            'monto' => (string) $g->monto,
            'categoria_gasto_id' => $g->categoria_gasto_id,
            'categoria_nombre' => $g->categoria?->nombre,
            'ambito' => $g->ambito ?: Gasto::AMBITO_SALON,
            'ambito_label' => $g->ambitoLabel(),
            'descripcion' => $g->descripcion,
            'fecha' => $g->fecha?->toDateString(),
            'metodo_pago_id' => $g->metodo_pago_id,
            'metodo_nombre' => $g->metodoPago?->nombre,
            'proveedor_id' => $g->proveedor_id,
            'proveedor_nombre' => $g->proveedor?->nombre,
            'estado' => $g->estado,
            'comprobante' => $g->comprobante,
        ];
        if ($detalle) {
            $base += [
                'creador_nombre' => $g->creador?->name,
                'compra_id' => $g->compra_id,
                'caja_movimiento_id' => $g->caja_movimiento_id,
            ];
        }

        return $base;
    }
}
