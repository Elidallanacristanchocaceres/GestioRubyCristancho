<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Permite el acceso solo a los roles indicados. El admin siempre pasa.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->esAdmin() || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        abort(403, 'No tiene permiso para acceder a esta sección.');
    }
}
