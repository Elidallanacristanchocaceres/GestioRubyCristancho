<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function __invoke(\App\Services\ReporteService $reportes): \Illuminate\View\View
    {
        $datos = $reportes->dashboard();

        return view('dashboard', $datos);
    }
}
