<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $proveedores = Proveedor::withCount('compras')
            ->when($request->filled('q'), function ($q) use ($request) {
                $busqueda = '%'.$request->str('q').'%';
                $q->where(function ($sub) use ($busqueda) {
                    $sub->where('nombre', 'like', $busqueda)
                        ->orWhere('nombre_marca', 'like', $busqueda)
                        ->orWhere('telefono', 'like', $busqueda);
                });
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->str('estado') === 'activos'))
            ->orderBy('nombre')
            ->paginate(15);

        return response()->json([
            'data' => $proveedores->getCollection()->map(fn ($p) => $this->resumen($p)),
            'meta' => ['current_page' => $proveedores->currentPage(), 'last_page' => $proveedores->lastPage(), 'total' => $proveedores->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $proveedor = Proveedor::create($this->validar($request));
        AuditoriaService::registrar('crear', 'proveedores', Proveedor::class, $proveedor->id, [], ['nombre' => $proveedor->nombre]);

        return response()->json(['data' => $this->resumen($proveedor)], 201);
    }

    public function show(Proveedor $proveedor): JsonResponse
    {
        $compras = $proveedor->compras()->withCount('detalles')->orderByDesc('fecha_compra')->paginate(12);
        $totalComprado = $proveedor->compras()->sum('total');
        $saldoPendiente = $proveedor->compras()->sum('saldo_pendiente');

        return response()->json([
            'data' => $this->resumen($proveedor, true),
            'total_comprado' => (string) $totalComprado,
            'saldo_pendiente' => (string) $saldoPendiente,
            'productos' => $proveedor->productos()->orderBy('nombre')->get(['id', 'nombre']),
            'compras' => collect($compras->items())->map(fn ($c) => [
                'id' => $c->id,
                'numero' => $c->numero ?? null,
                'fecha_compra' => $c->fecha_compra?->toDateString(),
                'estado' => $c->estado ?? null,
                'total' => $c->total !== null ? (string) $c->total : null,
                'total_pagado' => $c->total_pagado !== null ? (string) $c->total_pagado : null,
                'saldo_pendiente' => $c->saldo_pendiente !== null ? (string) $c->saldo_pendiente : null,
                'detalles_count' => $c->detalles_count ?? $c->detalles?->count(),
            ])->values(),
            'compras_meta' => [
                'current_page' => $compras->currentPage(),
                'last_page' => $compras->lastPage(),
                'total' => $compras->total(),
            ],
        ]);
    }

    public function update(Request $request, Proveedor $proveedor): JsonResponse
    {
        $antes = $proveedor->only(['nombre_marca', 'nombre', 'telefono']);
        $proveedor->update($this->validar($request));
        AuditoriaService::registrar('actualizar', 'proveedores', Proveedor::class, $proveedor->id, $antes, $proveedor->only(array_keys($antes)));

        return response()->json(['data' => $this->resumen($proveedor, true)]);
    }

    public function destroy(Proveedor $proveedor): JsonResponse
    {
        if ($proveedor->compras()->exists()) {
            $proveedor->update(['activo' => false]);
            AuditoriaService::registrar('actualizar', 'proveedores', Proveedor::class, $proveedor->id, ['activo' => true], ['activo' => false], 'Desactivación de proveedor');

            return response()->json(['message' => 'El proveedor tiene compras; se desactivó en lugar de eliminarlo.']);
        }

        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado correctamente.']);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre_marca' => ['nullable', 'string', 'max:200'],
            'nombre' => ['required', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:30'],
        ]) + ['activo' => $request->boolean('activo', true)];
    }

    private function resumen(Proveedor $p, bool $detalle = false): array
    {
        $base = [
            'id' => $p->id,
            'nombre_marca' => $p->nombre_marca,
            'nombre' => $p->nombre,
            'telefono' => $p->telefono,
            'activo' => (bool) $p->activo,
            'compras_count' => $p->compras_count ?? $p->compras()->count(),
        ];
        if ($detalle) {
            $base += [
                'total_comprado' => (string) $p->compras()->sum('total'),
                'saldo_pendiente' => (string) $p->compras()->sum('saldo_pendiente'),
            ];
        }

        return $base;
    }
}
