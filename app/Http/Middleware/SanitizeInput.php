<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Campos que no deben alterarse (p. ej. contraseñas).
     *
     * @var list<string>
     */
    private array $except = [
        'password',
        'password_confirmation',
        'current_password',
    ];

    /**
     * Limpia entradas de texto: elimina bytes nulos y etiquetas HTML para reducir riesgo de contenido malicioso.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->limpiar($request->all()));

        return $next($request);
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function limpiar($value, ?string $key = null)
    {
        if ($key !== null && in_array($key, $this->except, true)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->limpiar($v, is_string($k) ? $k : null);
            }

            return $value;
        }

        if (is_string($value)) {
            $value = str_replace("\0", '', $value);
            $value = strip_tags($value);

            return trim($value);
        }

        return $value;
    }
}