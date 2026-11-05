<?php

namespace App\Http\Controllers;

use App\Models\AgendaEstilista;
use App\Models\Consumo;
use App\Models\Estilista;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstilistaController extends Controller
{
    public function index(): View
    {
        $estilistas = Estilista::withCount('consumos')->orderBy('nombre')->paginate(15);

        return view('estilistas.index', compact('estilistas'));
    }

    public function create(): View
    {
        return view('estilistas.create', ['estilista' => new Estilista(['activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $estilista = Estilista::create($this->validar($request));

        return redirect()->route('estilistas.show', $estilista)->with('exito', 'Profesional registrado correctamente.');
    }

    public function show(Estilista $estilista): View
    {
        $registros = Consumo::with(['cliente', 'servicio'])
            ->where('estilista_id', $estilista->id)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(12);

        return view('estilistas.show', compact('estilista', 'registros'));
    }

    public function edit(Estilista $estilista): View
    {
        return view('estilistas.edit', compact('estilista'));
    }

    public function update(Request $request, Estilista $estilista): RedirectResponse
    {
        $estilista->update($this->validar($request));

        return redirect()->route('estilistas.show', $estilista)->with('exito', 'Profesional actualizado correctamente.');
    }

    public function destroy(Estilista $estilista): RedirectResponse
    {
        if ($estilista->citas()->exists()) {
            $estilista->update(['activo' => false]);

            return redirect()->route('estilistas.index')->with('exito', 'El profesional tiene citas; se desactivó en lugar de eliminarlo.');
        }

        $estilista->delete();

        return redirect()->route('estilistas.index')->with('exito', 'Profesional eliminado.');
    }

    public function guardarAgenda(Request $request, Estilista $estilista): RedirectResponse
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

        return back()->with('exito', 'Agenda del profesional actualizada.');
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
}
