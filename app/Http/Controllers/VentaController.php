<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Estilista;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function __construct(private readonly VentaService $ventas) {}

    public function index(Request $request): View
    {
        $ventas = Venta::with(['cliente', 'detalles'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')))
            ->when($request->filled('q'), fn ($q) => $q->where('numero', 'like', '%'.$request->str('q').'%'))
            ->orderByDesc('fecha_hora')
            ->paginate(15)
            ->withQueryString();

        return view('ventas.index', compact('ventas'));
    }

    public function create(Request $request): View
    {
        return view('ventas.create', [
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(),
            'servicios' => Servicio::where('activo', true)->with('productos.producto')->orderBy('nombre')->get(),
            'productos' => Producto::where('activo', true)
                ->whereIn('tipo_uso', [Producto::USO_VENTA, Producto::USO_AMBOS])
                ->orderBy('nombre')
                ->get(),
            'metodos' => MetodoPago::orderBy('nombre')->get(),
            'cita' => $request->filled('cita') ? \App\Models\Cita::with(['cliente', 'servicio', 'estilista'])->find($request->integer('cita')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'estilista_id' => ['nullable', 'exists:estilistas,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.tipo' => ['required', 'in:servicio,producto'],
            'detalles.*.servicio_id' => ['nullable', 'exists:servicios,id'],
            'detalles.*.producto_id' => ['nullable', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'consumos' => ['array'],
            'consumos.*.producto_id' => ['nullable', 'exists:productos,id'],
            'consumos.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'pagos' => ['nullable', 'array'],
            'pagos.*.metodo_pago_id' => ['required_with:pagos', 'exists:metodo_pagos,id'],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'min:0'],
        ]);

        // Asocia consumos declarados al detalle de servicio (el primero).
        $detalles = $datos['detalles'];
        if (! empty($datos['consumos'])) {
            $consumos = array_values(array_filter($datos['consumos'], fn ($c) => ! empty($c['producto_id']) && (float) $c['cantidad'] > 0));
            foreach ($detalles as $i => $detalle) {
                if (($detalle['tipo'] ?? null) === 'servicio') {
                    $detalles[$i]['consumos'] = $consumos;
                    break;
                }
            }
        }

        $venta = $this->ventas->crear([
            'cliente_id' => $datos['cliente_id'] ?? null,
            'cita_id' => $datos['cita_id'] ?? null,
            'estilista_id' => $datos['estilista_id'] ?? null,
            'descuento' => $datos['descuento'] ?? '0',
            'notas' => $datos['notas'] ?? null,
            'detalles' => $detalles,
        ]);

        $pagos = array_values(array_filter($datos['pagos'] ?? [], fn ($p) => (float) ($p['monto'] ?? 0) > 0));
        if ($pagos) {
            $this->ventas->registrarPagos($venta, $pagos, false);
        }

        return redirect()->route('ventas.show', $venta)->with('exito', 'Venta registrada correctamente.');
    }

    public function show(Venta $venta): View
    {
        $venta->load(['cliente', 'cita', 'estilista', 'detalles.servicio', 'detalles.producto', 'pagos.metodoPago', 'consumos.producto']);

        return view('ventas.show', [
            'venta' => $venta,
            'metodos' => MetodoPago::orderBy('nombre')->get(),
        ]);
    }

    public function registrarPago(Request $request, Venta $venta): RedirectResponse
    {
        $datos = $request->validate([
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.metodo_pago_id' => ['required', 'exists:metodo_pagos,id'],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:120'],
        ]);

        $this->ventas->registrarPagos($venta, $datos['pagos'], true);

        return back()->with('exito', 'Pago registrado correctamente.');
    }

    public function anular(Request $request, Venta $venta): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:300']]);
        $this->ventas->anular($venta, $datos['motivo']);

        return redirect()->route('ventas.show', $venta)->with('exito', 'Venta anulada y stock revertido.');
    }

    public function recibo(Venta $venta): View
    {
        $venta->load(['cliente', 'detalles', 'pagos.metodoPago', 'estilista']);

        return view('ventas.recibo', compact('venta'));
    }
}
