<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clientes = Cliente::query()
            ->buscar($request->str('q')->toString())
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->str('estado') === 'activos'))
            ->orderBy('nombre')
            ->paginate(15);

        return response()->json([
            'data' => $clientes->getCollection()->map(fn ($c) => $this->resumen($c)),
            'meta' => ['current_page' => $clientes->currentPage(), 'last_page' => $clientes->lastPage(), 'total' => $clientes->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $cliente = Cliente::create($this->validar($request));
        AuditoriaService::registrar('crear', 'clientes', Cliente::class, $cliente->id, [], ['nombre' => $cliente->nombre]);

        return response()->json(['data' => $this->resumen($cliente)], 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        $consumos = $cliente->consumos()->with(['servicio', 'estilista'])->orderByDesc('fecha')->orderByDesc('id')->paginate(10, ['*'], 'consumos');
        $ventas = $cliente->ventas()->with('detalles')->orderByDesc('fecha')->orderByDesc('id')->paginate(10, ['*'], 'ventas');

        return response()->json([
            'data' => $this->resumen($cliente, true),
            'consumos' => collect($consumos->items())->map(fn ($c) => [
                'id' => $c->id,
                'fecha' => $c->fecha?->toDateString(),
                'descripcion' => $c->descripcion,
                'estado' => $c->estado,
                'precio_cobrado' => $c->precio_cobrado !== null ? (string) $c->precio_cobrado : null,
                'costo_total' => $c->costo_total !== null ? (string) $c->costo_total : null,
                'servicio_nombre' => $c->servicio?->nombre,
                'estilista_nombre' => $c->estilista?->nombre,
            ])->values(),
            'ventas' => collect($ventas->items())->map(fn ($v) => [
                'id' => $v->id,
                'numero' => $v->numero,
                'fecha' => $v->fecha?->toDateString(),
                'estado' => $v->estado,
                'total' => $v->total !== null ? (string) $v->total : null,
                'total_pagado' => $v->total_pagado !== null ? (string) $v->total_pagado : null,
                'saldo_pendiente' => $v->saldo_pendiente !== null ? (string) $v->saldo_pendiente : null,
                'detalles_count' => $v->detalles?->count(),
            ])->values(),
        ]);
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $antes = $cliente->only(['nombre', 'celular', 'email', 'direccion']);
        $cliente->update($this->validar($request, $cliente));
        AuditoriaService::registrar('actualizar', 'clientes', Cliente::class, $cliente->id, $antes, $cliente->only(array_keys($antes)));

        return response()->json(['data' => $this->resumen($cliente, true)]);
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        $cliente->update(['activo' => false]);
        AuditoriaService::registrar('actualizar', 'clientes', Cliente::class, $cliente->id, ['activo' => true], ['activo' => false], 'Desactivación de cliente');

        return response()->json(['message' => 'Cliente desactivado; su historial se conserva.']);
    }

    public function reactivar(Cliente $cliente): JsonResponse
    {
        $cliente->update(['activo' => true]);

        return response()->json(['data' => $this->resumen($cliente, true)]);
    }

    private function validar(Request $request, ?Cliente $cliente = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'celular' => ['required', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string'],
            'preferencias_cabello' => ['nullable', 'string'],
            'formula_color' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]) + ['activo' => $request->boolean('activo', $cliente?->activo ?? true)];
    }

    private function resumen(Cliente $c, bool $detalle = false): array
    {
        $base = [
            'id' => $c->id, 'nombre' => $c->nombre, 'celular' => $c->celular,
            'email' => $c->email, 'saldo_pendiente' => (string) ($c->saldo_pendiente ?? '0.00'),
            'visitas' => $c->pagosVisitas(), 'activo' => (bool) $c->activo,
        ];
        if ($detalle) {
            $base += [
                'whatsapp' => $c->whatsapp, 'fecha_nacimiento' => $c->fecha_nacimiento?->toDateString(),
                'direccion' => $c->direccion, 'observaciones' => $c->observaciones,
                'preferencias_cabello' => $c->preferencias_cabello, 'formula_color' => $c->formula_color,
            ];
        }

        return $base;
    }
}
