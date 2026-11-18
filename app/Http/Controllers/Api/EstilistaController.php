<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgendaEstilista;
use App\Models\Consumo;
use App\Models\Estilista;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstilistaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $estilistas = Estilista::withCount('consumos')
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = $request->str('q');
                $q->where(function ($sub) use ($t) {
                    $sub->where('nombre', 'like', "%{$t}%")
                        ->orWhere('especialidad', 'like', "%{$t}%")
                        ->orWhere('telefono', 'like', "%{$t}%");
                });
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->str('estado') === 'activos'))
            ->orderBy('nombre')
            ->paginate(15);

        return response()->json([
            'data' => $estilistas->getCollection()->map(fn ($e) => $this->resumen($e)),
            'meta' => ['current_page' => $estilistas->currentPage(), 'last_page' => $estilistas->lastPage(), 'total' => $estilistas->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $estilista = Estilista::create($this->validar($request));
        AuditoriaService::registrar('crear', 'estilistas', Estilista::class, $estilista->id, [], ['nombre' => $estilista->nombre]);

        return response()->json(['data' => $this->resumen($estilista)], 201);
    }

    public function show(Estilista $estilista): JsonResponse
    {
        $registros = Consumo::with(['cliente', 'servicio'])
            ->where('estilista_id', $estilista->id)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(12);

        $agenda = $estilista->agenda()->orderBy('dia_semana')->orderBy('hora_inicio')->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'dia_semana' => $a->dia_semana,
                'dia_label' => $a->diaLabel(),
                'hora_inicio' => $a->hora_inicio,
                'hora_fin' => $a->hora_fin,
                'disponible' => (bool) $a->disponible,
            ])->values();

        return response()->json([
            'data' => $this->resumen($estilista, true),
            'agenda' => $agenda,
            'registros' => collect($registros->items())->map(fn ($r) => [
                'id' => $r->id,
                'fecha' => $r->fecha?->toDateString(),
                'descripcion' => $r->descripcion,
                'estado' => $r->estado,
                'precio_cobrado' => $r->precio_cobrado !== null ? (string) $r->precio_cobrado : null,
                'costo_total' => $r->costo_total !== null ? (string) $r->costo_total : null,
                'cliente_nombre' => $r->cliente?->nombre,
                'servicio_nombre' => $r->servicio?->nombre,
            ])->values(),
            'registros_meta' => [
                'current_page' => $registros->currentPage(),
                'last_page' => $registros->lastPage(),
                'total' => $registros->total(),
            ],
        ]);
    }

    public function update(Request $request, Estilista $estilista): JsonResponse
    {
        $antes = $estilista->only(['nombre', 'especialidad', 'telefono', 'fecha_contratacion', 'salario_base']);
        $estilista->update($this->validar($request));
        AuditoriaService::registrar('actualizar', 'estilistas', Estilista::class, $estilista->id, $antes, $estilista->only(array_keys($antes)));

        return response()->json(['data' => $this->resumen($estilista, true)]);
    }

    public function destroy(Estilista $estilista): JsonResponse
    {
        if ($estilista->citas()->exists()) {
            $estilista->update(['activo' => false]);
            AuditoriaService::registrar('actualizar', 'estilistas', Estilista::class, $estilista->id, ['activo' => true], ['activo' => false], 'Desactivación de profesional');

            return response()->json(['message' => 'El profesional tiene citas; se desactivó en lugar de eliminarlo.']);
        }

        $estilista->delete();

        return response()->json(['message' => 'Profesional eliminado.']);
    }

    public function guardarAgenda(Request $request, Estilista $estilista): JsonResponse
    {
        $datos = $request->validate([
            'agenda' => ['array'],
            'agenda.*.dia_semana' => ['required', 'integer', 'between:0,6'],
            'agenda.*.hora_inicio' => ['required'],
            'agenda.*.hora_fin' => ['required'],
        ]);

        $estilista->agenda()->delete();

        foreach ($datos['agenda'] ?? [] as $franja) {
            AgendaEstilista::create([
                'estilista_id' => $estilista->id,
                'dia_semana' => $franja['dia_semana'],
                'hora_inicio' => $franja['hora_inicio'],
                'hora_fin' => $franja['hora_fin'],
                'disponible' => true,
            ]);
        }

        return response()->json([
            'message' => 'Agenda del profesional actualizada.',
            'data' => $estilista->agenda()->orderBy('dia_semana')->orderBy('hora_inicio')->get()->map(fn ($a) => [
                'id' => $a->id,
                'dia_semana' => $a->dia_semana,
                'dia_label' => $a->diaLabel(),
                'hora_inicio' => $a->hora_inicio,
                'hora_fin' => $a->hora_fin,
                'disponible' => (bool) $a->disponible,
            ])->values(),
        ]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'especialidad' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'fecha_contratacion' => ['required', 'date'],
            'salario_base' => ['nullable', 'numeric', 'min:0'],
        ]) + ['activo' => $request->boolean('activo', true)];
    }

    private function resumen(Estilista $e, bool $detalle = false): array
    {
        $base = [
            'id' => $e->id,
            'nombre' => $e->nombre,
            'especialidad' => $e->especialidad,
            'telefono' => $e->telefono,
            'fecha_contratacion' => $e->fecha_contratacion?->toDateString(),
            'salario_base' => $e->salario_base !== null ? (string) $e->salario_base : null,
            'activo' => (bool) $e->activo,
            'consumos_count' => $e->consumos_count ?? $e->consumos()->count(),
        ];
        if ($detalle) {
            $base += [
                'agenda' => $e->agenda()->orderBy('dia_semana')->orderBy('hora_inicio')->get()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'dia_semana' => $a->dia_semana,
                        'dia_label' => $a->diaLabel(),
                        'hora_inicio' => $a->hora_inicio,
                        'hora_fin' => $a->hora_fin,
                        'disponible' => (bool) $a->disponible,
                    ])->values(),
            ];
        }

        return $base;
    }
}
