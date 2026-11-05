<?php

namespace App\Http\Controllers;

use App\Services\ReporteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReporteController extends Controller
{
    public function index(): View
    {
        return view('reportes.index');
    }

    public function resultados(Request $request, ReporteService $reportes): View
    {
        [$desde, $hasta] = $this->rango($request);

        return view('reportes.resultados', [
            'desde' => $desde,
            'hasta' => $hasta,
            'resultado' => $reportes->estadoResultados($desde, $hasta),
            'serie' => $reportes->serieDiaria(30),
        ]);
    }

    public function inventario(Request $request, ReporteService $reportes): View
    {
        return view('reportes.inventario', [
            'inventario' => $reportes->inventarioValorizado(),
        ]);
    }

    public function consumo(Request $request, ReporteService $reportes): View
    {
        [$desde, $hasta] = $this->rango($request);

        return view('reportes.consumo', [
            'desde' => $desde,
            'hasta' => $hasta,
            'productos' => $reportes->productosMasUtilizados($desde, $hasta, 20),
            'servicios' => $reportes->serviciosMasVendidos($desde, $hasta, 20),
        ]);
    }

    public function cartera(ReporteService $reportes): View
    {
        return view('reportes.cartera', [
            'porCobrar' => \App\Models\Venta::with('cliente')->where('saldo_pendiente', '>', 0)->whereNotIn('estado', ['anulada'])->orderByDesc('saldo_pendiente')->get(),
            'porPagar' => \App\Models\Compra::with('proveedor')->where('saldo_pendiente', '>', 0)->orderByDesc('saldo_pendiente')->get(),
            'totalCobrar' => $reportes->cuentasPorCobrar(),
            'totalPagar' => $reportes->cuentasPorPagar(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rango(Request $request): array
    {
        $desde = $request->filled('desde') ? Carbon::parse($request->date('desde'))->toDateString() : Carbon::today()->startOfMonth()->toDateString();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->date('hasta'))->toDateString() : Carbon::today()->toDateString();

        if (Carbon::parse($desde)->gt(Carbon::parse($hasta))) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }
}
