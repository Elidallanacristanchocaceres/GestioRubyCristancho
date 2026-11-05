<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $registros = Auditoria::with('usuario')
            ->when($request->filled('modulo'), fn ($q) => $q->where('modulo', $request->str('modulo')))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->str('accion')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('auditoria.index', [
            'registros' => $registros,
            'modulos' => Auditoria::query()->distinct()->pluck('modulo')->filter()->sort()->values(),
        ]);
    }
}
