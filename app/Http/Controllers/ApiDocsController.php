<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Documentación interactiva de la API (Swagger UI) sin vistas Blade.
 */
class ApiDocsController extends Controller
{
    public function info(): JsonResponse
    {
        return response()->json([
            'app' => 'GestioRubyCristancho API',
            'version' => '1.0.0',
            'docs' => url('/docs'),
            'api' => url('/api'),
        ]);
    }

    public function index(): Response
    {
        $archivo = resource_path('docs/index.html');

        abort_unless(is_file($archivo), 404, 'La documentación no está disponible.');

        return response(file_get_contents($archivo), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function spec(): JsonResponse
    {
        $paths = [];
        $tags = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! Str::startsWith($uri, 'api/') || $uri === 'api/docs') {
                continue;
            }

            $metodos = array_values(array_diff($route->methods(), ['HEAD']));
            if ($metodos === []) {
                continue;
            }

            $path = '/'.$uri;
            $tag = Str::of($uri)
                ->after('api/')
                ->before('/')
                ->headline()
                ->value() ?: 'General';
            $tags[$tag] = true;

            $operation = [
                'tags' => [$tag],
                'summary' => $route->getName() ?: strtoupper($metodos[0]).' '.$path,
                'operationId' => $route->getName() ?: Str::slug($metodos[0].'-'.$path),
                'parameters' => [],
                'responses' => [
                    '200' => ['description' => 'Respuesta correcta'],
                    '401' => ['description' => 'No autenticado'],
                    '403' => ['description' => 'Sin permiso'],
                    '422' => ['description' => 'Datos inválidos'],
                ],
            ];

            preg_match_all('/\{([^}]+)\}/', $uri, $coincidencias);
            foreach ($coincidencias[1] as $parametro) {
                $operation['parameters'][] = [
                    'name' => $parametro,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ];
            }

            if (in_array($metodos[0], ['POST', 'PUT', 'PATCH'], true)) {
                $operation['requestBody'] = [
                    'required' => false,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => true,
                            ],
                        ],
                    ],
                ];
            }

            if (in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                $operation['security'] = [['sanctum' => []]];
            }

            foreach ($metodos as $metodo) {
                $paths[$path][strtolower($metodo)] = $operation;
            }
        }

        ksort($paths);

        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'GestioRubyCristancho API',
                'version' => '1.0.0',
                'description' => 'API JSON para la SPA Angular. Autenticación con Laravel Sanctum (cookie de sesión + CSRF).',
            ],
            'servers' => [['url' => url('/')]],
            'tags' => array_map(fn (string $tag) => ['name' => $tag], array_keys($tags)),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => 'laravel_session',
                        'description' => 'Sesión de Laravel Sanctum. Inicia sesión con POST /api/login.',
                    ],
                ],
            ],
        ]);
    }
}
