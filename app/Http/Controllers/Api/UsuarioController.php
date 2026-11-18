<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = User::orderBy('name')->paginate(15);

        return response()->json([
            'data' => $usuarios->getCollection()->map(fn ($u) => $this->resumen($u)),
            'meta' => ['current_page' => $usuarios->currentPage(), 'last_page' => $usuarios->lastPage(), 'total' => $usuarios->total()],
        ]);
    }

    public function show(User $usuario): JsonResponse
    {
        return response()->json(['data' => $this->resumen($usuario, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $datos['password'] = Hash::make($datos['password']);

        $creado = User::create($datos);

        return response()->json(['data' => $this->resumen($creado, true)], 201);
    }

    public function update(Request $request, User $usuario): JsonResponse
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

        return response()->json(['data' => $this->resumen($usuario->fresh(), true)]);
    }

    public function destroy(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->id === $request->user()->id) {
            return response()->json(['message' => 'No puede eliminar su propio usuario.'], 422);
        }

        if ($usuario->esAdmin() && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['message' => 'Debe existir al menos un administrador.'], 422);
        }

        AuditoriaService::registrar('eliminar', 'usuarios', User::class, $usuario->id, $usuario->only(['name', 'email', 'role']));
        $usuario->delete();

        return response()->json(['message' => 'Usuario eliminado.']);
    }

    /**
     * Matriz de permisos por rol (GET).
     */
    public function permisos(): JsonResponse
    {
        $permisos = Permiso::orderBy('grupo')->orderBy('nombre')->get()->groupBy('grupo');
        $roles = ['empleado', 'invitado'];

        $matriz = DB::table('permiso_roles')->get()->groupBy('role');

        $grupos = $permisos->map(fn ($lista) => $lista->map(fn ($p) => [
            'id' => $p->id,
            'clave' => $p->clave,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
        ])->values())->all();

        $seleccion = [];
        foreach ($roles as $role) {
            $seleccion[$role] = isset($matriz[$role])
                ? $matriz[$role]->where('permitido', true)->pluck('permiso_id')->values()->all()
                : [];
        }

        return response()->json([
            'roles' => $roles,
            'roles_labels' => [
                'empleado' => 'Empleado',
                'invitado' => 'Consultor (Contadores)',
            ],
            'permisos' => $grupos,
            'matriz' => $seleccion,
        ]);
    }

    public function guardarPermisos(Request $request): JsonResponse
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

        return response()->json(['message' => 'Permisos actualizados correctamente.']);
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cedula' => ['required', 'string', 'max:20', Rule::unique('users', 'cedula')->ignore($usuario?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_EMPLEADO, User::ROLE_INVITADO])], // invitado = Consultor (Contadores)
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'cedula.required' => 'La cédula es obligatoria.',
            'cedula.unique' => 'Ya existe un usuario registrado con esta cédula.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Ya existe un usuario registrado con este correo electrónico.',
            'role.required' => 'El rol es obligatorio.',
            'role.in' => 'El rol seleccionado no es válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
        ], [
            'name' => 'nombre',
            'cedula' => 'cédula',
            'email' => 'correo electrónico',
            'role' => 'rol',
            'password' => 'contraseña',
        ]);
    }

    private function resumen(User $u, bool $detalle = false): array
    {
        $base = [
            'id' => $u->id,
            'name' => $u->name,
            'cedula' => $u->cedula,
            'email' => $u->email,
            'role' => $u->role,
            'rol_label' => $u->rolLabel(),
            'es_admin' => $u->esAdmin(),
        ];

        return $base;
    }
}
