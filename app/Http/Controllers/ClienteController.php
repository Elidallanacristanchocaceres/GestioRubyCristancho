<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::query()
            ->buscar($request->str('q')->toString())
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->str('estado') === 'activos'))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function create(): View
    {
        return view('clientes.create', ['cliente' => new Cliente(['activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cliente = Cliente::create($this->validar($request));

        AuditoriaService::registrar('crear', 'clientes', Cliente::class, $cliente->id, [], ['nombre' => $cliente->nombre]);

        return redirect()->route('clientes.show', $cliente)
            ->with('exito', 'Cliente registrado correctamente.');
    }

    public function show(Cliente $cliente): View
    {
        $cliente->load([
            'ventas' => fn ($q) => $q->with('detalles')->orderByDesc('fecha'),
        ]);

        $consumos = $cliente->consumos()->with(['servicio', 'estilista'])->orderByDesc('fecha')->orderByDesc('id')->paginate(10, ['*'], 'consumos');
        $ventas = $cliente->ventas()->with('detalles')->orderByDesc('fecha')->paginate(10, ['*'], 'ventas');

        return view('clientes.show', compact('cliente', 'consumos', 'ventas'));
    }

    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $antes = $cliente->only(['nombre', 'celular', 'email', 'direccion']);
        $cliente->update($this->validar($request, $cliente));

        AuditoriaService::registrar('actualizar', 'clientes', Cliente::class, $cliente->id, $antes, $cliente->only(array_keys($antes)));

        return redirect()->route('clientes.show', $cliente)
            ->with('exito', 'Cliente actualizado correctamente.');
    }

    /**
     * Desactiva al cliente conservando todo su historial.
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->update(['activo' => false]);

        AuditoriaService::registrar('actualizar', 'clientes', Cliente::class, $cliente->id, ['activo' => true], ['activo' => false], 'Desactivación de cliente');

        return redirect()->route('clientes.index')
            ->with('exito', 'Cliente desactivado; su historial se conserva.');
    }

    public function reactivar(Cliente $cliente): RedirectResponse
    {
        $cliente->update(['activo' => true]);

        return back()->with('exito', 'Cliente reactivado.');
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
        ], [], [
            'nombre' => 'nombre',
            'celular' => 'celular',
            'fecha_nacimiento' => 'fecha de nacimiento',
        ]) + ['activo' => $request->boolean('activo', $cliente?->activo ?? true)];
    }
}
