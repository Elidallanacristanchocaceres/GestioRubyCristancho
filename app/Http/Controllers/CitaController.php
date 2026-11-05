<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Estilista;
use App\Models\Servicio;
use App\Services\CitaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CitaController extends Controller
{
    public function __construct(private readonly CitaService $citas) {}

    public function index(Request $request): View
    {
        $vista = $request->str('vista', 'dia')->toString();
        $fecha = $request->filled('fecha') ? Carbon::parse($request->date('fecha')) : Carbon::today();

        if (! in_array($vista, ['dia', 'semana', 'mes', 'lista'], true)) {
            $vista = 'dia';
        }

        $query = Cita::with(['cliente', 'estilista', 'servicio'])
            ->when($request->filled('estilista'), fn ($q) => $q->where('estilista_id', $request->integer('estilista')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')));

        if ($vista === 'dia') {
            $query->whereDate('fecha_hora', $fecha->toDateString());
        } elseif ($vista === 'semana') {
            $query->whereBetween('fecha_hora', [$fecha->copy()->startOfWeek(), $fecha->copy()->endOfWeek()]);
        } elseif ($vista === 'mes') {
            $query->whereBetween('fecha_hora', [$fecha->copy()->startOfMonth(), $fecha->copy()->endOfMonth()]);
        }

        $citas = $query->orderBy('fecha_hora')->paginate($vista === 'lista' ? 20 : 50)->withQueryString();

        return view('citas.index', [
            'citas' => $citas,
            'vista' => $vista,
            'fecha' => $fecha,
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('citas.create', $this->opciones() + [
            'cita' => new Cita([
                'fecha_hora' => $request->filled('fecha') ? Carbon::parse($request->date('fecha'))->setTime(9, 0) : now()->addDay()->setTime(9, 0),
                'estado' => Cita::ESTADO_PENDIENTE,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $cita = $this->citas->crear($datos);

        return redirect()->route('citas.show', $cita)->with('exito', 'Cita registrada correctamente.');
    }

    public function show(Cita $cita): View
    {
        $cita->load(['cliente', 'estilista', 'servicio', 'venta.detalles', 'venta.pagos', 'consumos.producto', 'pagos.metodoPago']);

        return view('citas.show', [
            'cita' => $cita,
            'historialCliente' => Cita::where('cliente_id', $cita->cliente_id)
                ->where('id', '!=', $cita->id)
                ->orderByDesc('fecha_hora')
                ->limit(5)
                ->get(),
        ]);
    }

    public function edit(Cita $cita): View
    {
        return view('citas.edit', $this->opciones() + compact('cita'));
    }

    public function update(Request $request, Cita $cita): RedirectResponse
    {
        $this->citas->actualizar($cita, $this->validar($request, $cita));

        return redirect()->route('citas.show', $cita)->with('exito', 'Cita actualizada correctamente.');
    }

    public function destroy(Request $request, Cita $cita): RedirectResponse
    {
        $motivo = $request->str('motivo')->toString() ?: 'Cancelación de cita';
        $this->citas->cancelar($cita, $motivo);

        return redirect()->route('citas.index')->with('exito', 'Cita cancelada correctamente.');
    }

    /**
     * Cambio rápido de estado (pendiente -> confirmada -> en proceso -> finalizada).
     */
    public function cambiarEstado(Request $request, Cita $cita): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:pendiente,confirmada,en_progreso,completada,cancelada'],
        ]);

        if ($datos['estado'] === Cita::ESTADO_CANCELADA) {
            $this->citas->cancelar($cita, $request->str('motivo')->toString() ?: 'Cambio de estado');

            return back()->with('exito', 'Cita cancelada.');
        }

        $cita->update(['estado' => $datos['estado']]);

        return back()->with('exito', 'Estado actualizado.');
    }

    private function opciones(): array
    {
        return [
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(),
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    private function validar(Request $request, ?Cita $cita = null): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'estilista_id' => ['required', 'exists:estilistas,id'],
            'servicio_id' => ['required', 'exists:servicios,id'],
            'fecha_hora' => ['required', 'date'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:600'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:pendiente,confirmada,en_progreso,completada,cancelada'],
            'notas' => ['nullable', 'string'],
        ], [], [
            'cliente_id' => 'cliente',
            'estilista_id' => 'profesional',
            'servicio_id' => 'servicio',
            'fecha_hora' => 'fecha y hora',
        ]);
    }
}
