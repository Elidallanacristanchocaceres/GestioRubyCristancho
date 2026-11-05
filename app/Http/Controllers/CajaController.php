<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\MetodoPago;
use App\Services\CajaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CajaController extends Controller
{
    public function __construct(private readonly CajaService $cajas) {}

    public function index(Request $request): View
    {
        $cajaActiva = Caja::cajaAbierta();

        $historial = Caja::with(['creador', 'cerrador'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_apertura', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_apertura', '<=', $request->date('hasta')))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $movimientos = $cajaActiva
            ? $cajaActiva->movimientos()->with(['metodoPago', 'creador'])->orderByDesc('id')->paginate(20, ['*'], 'mov')
            : null;

        $saldosMetodo = collect();
        if ($cajaActiva) {
            $saldosMetodo = MetodoPago::orderBy('id')->get()->map(fn ($m) => [
                'metodo' => $m,
                'esperado' => $cajaActiva->saldoEsperadoPorMetodo($m->id),
            ]);
        }

        return view('caja.index', compact('cajaActiva', 'historial', 'movimientos', 'saldosMetodo'));
    }

    public function create(): View
    {
        if (Caja::cajaAbierta()) {
            return view('caja.index')->with('error', 'Ya existe una caja abierta.');
        }

        return view('caja.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'saldo_inicial' => ['required', 'numeric', 'min:0'],
            'nota_apertura' => ['nullable', 'string', 'max:500'],
        ]);

        $this->cajas->abrir((string) $datos['saldo_inicial'], $datos['nota_apertura'] ?? null);

        return redirect()->route('caja.index')->with('exito', 'Caja abierta correctamente.');
    }

    public function show(Caja $caja): View
    {
        $caja->load(['movimientos.metodoPago', 'movimientos.creador', 'cierreDetalles.metodoPago', 'creador', 'cerrador']);
        $saldosMetodo = MetodoPago::orderBy('id')->get()->map(fn ($m) => [
            'metodo' => $m,
            'esperado' => $caja->saldoEsperadoPorMetodo($m->id),
        ]);

        return view('caja.show', compact('caja', 'saldosMetodo'));
    }

    public function movimiento(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:ingreso,egreso'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['required', 'exists:metodo_pagos,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        $this->cajas->movimientoManual(
            $datos['tipo'],
            (string) $datos['monto'],
            (int) $datos['metodo_pago_id'],
            $datos['concepto'],
            $datos['descripcion'] ?? null
        );

        return back()->with('exito', 'Movimiento de caja registrado.');
    }

    public function cerrar(Request $request, Caja $caja): RedirectResponse
    {
        $datos = $request->validate([
            'conteos' => ['required', 'array'],
            'conteos.*' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['array'],
            'notas_cierre' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->cajas->cerrar($caja, $datos['conteos'], $datos['notas_cierre'] ?? null, $datos['observaciones'] ?? []);

        return redirect()->route('caja.show', $caja)->with('exito', 'Caja cerrada correctamente.');
    }

    public function reabrir(Request $request, Caja $caja): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:300']]);
        $this->cajas->reabrir($caja, $datos['motivo']);

        return redirect()->route('caja.show', $caja)->with('exito', 'Caja reabierta (con auditoría).');
    }
}
