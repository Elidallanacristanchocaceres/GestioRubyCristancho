<?php

use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Backend API-only
|--------------------------------------------------------------------------
|
| La interfaz vive en la SPA Angular (frontend/). Blade se eliminó: aquí
| solo quedan la raíz informativa y la documentación tipo Swagger.
|
*/

Route::get('/', [ApiDocsController::class, 'info'])->name('api.info');

Route::redirect('/api/docs', '/docs');

Route::get('/docs', [ApiDocsController::class, 'index'])->name('docs');
Route::get('/docs/openapi.json', [ApiDocsController::class, 'spec'])->name('docs.openapi');
