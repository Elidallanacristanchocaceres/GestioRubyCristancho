<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(): View
    {
        $usuarios = User::orderBy('name')->paginate(15);

        return view('usuarios.index', compact('usuarios'));
    }

    public function create(): View
    {
        return view('usuarios.create', ['usuario' => new User(['role' => User::ROLE_EMPLEADO])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['password'] = Hash::make($datos['password']);
        User::create($datos);

        return redirect()->route('usuarios.index')->with('exito', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.edit', compact('usuario'));
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        if (! empty($datos['password'])) {
            $datos['password'] = Hash::make($datos['password']);
        } else {
            unset($datos['password']);
        }

        $antes = $usuario->only(['name', 'email', 'role', 'cedula']);
        $usuario->update($datos);

        AuditoriaService::registrar('actualizar', 'usuarios', User::class, $usuario->id, $antes, $usuario->only(array_keys($antes)));

        return redirect()->route('usuarios.index')->with('exito', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puede eliminar su propio usuario.');
        }

        if ($usuario->esAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Debe existir al menos un administrador.');
        }

        AuditoriaService::registrar('eliminar', 'usuarios', User::class, $usuario->id, $usuario->only(['name', 'email', 'role']));
        $usuario->delete();

        return redirect()->route('usuarios.index')->with('exito', 'Usuario eliminado.');
    }

    /**
     * Matriz de permisos por rol.
     */
    public function permisos(): View
    {
        $permisos = Permiso::orderBy('grupo')->orderBy('nombre')->get()->groupBy('grupo');
        $roles = ['empleado', 'invitado'];

        $matriz = DB::table('permiso_roles')->get()->groupBy('role');

        return view('usuarios.permisos', compact('permisos', 'roles', 'matriz'));
    }

    public function guardarPermisos(Request $request): RedirectResponse
    {
        $request->validate([
            'permisos' => ['array'],
            'permisos.*' => ['array'],
        ]);

        $seleccionados = $request->input('permisos', []);

        foreach (Permiso::all() as $permiso) {
            foreach (['empleado', 'invitado'] as $role) {
                DB::table('permiso_roles')->updateOrInsert(
                    ['permiso_id' => $permiso->id, 'role' => $role],
                    [
                        'permitido' => in_array((string) $permiso->id, array_map('strval', $seleccionados[$role] ?? []), true),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        AuditoriaService::registrar('actualizar', 'usuarios', Permiso::class, null, [], ['permisos' => 'actualizados']);

        return back()->with('exito', 'Permisos actualizados correctamente.');
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cedula' => ['required', 'string', 'max:20', Rule::unique('users', 'cedula')->ignore($usuario?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_EMPLEADO, User::ROLE_INVITADO])],
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8'],
        ], [], ['name' => 'nombre', 'cedula' => 'cédula', 'role' => 'rol']);
    }
}
