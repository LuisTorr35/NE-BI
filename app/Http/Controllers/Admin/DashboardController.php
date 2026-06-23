<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $total = Customer::count();

        // Conteo por nivel de riesgo
        $porNivel = Customer::select('churn_level', DB::raw('COUNT(*) as n'))
            ->whereNotNull('churn_level')
            ->groupBy('churn_level')
            ->pluck('n', 'churn_level');

        // Riesgo por categoria favorita (cuantos en alto/medio)
        $porCategoria = Customer::select(
                'prefered_order_cat',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN churn_level IN ('alto','medio') THEN 1 ELSE 0 END) as en_riesgo")
            )
            ->whereNotNull('prefered_order_cat')
            ->groupBy('prefered_order_cat')
            ->orderByDesc('en_riesgo')
            ->get();

        // Riesgo por ciudad (CityTier)
        $porCiudad = Customer::select(
                'city_tier',
                DB::raw('COUNT(*) as total'),
                DB::raw('ROUND(AVG(churn_probability)*100,1) as prob_media')
            )
            ->whereNotNull('city_tier')
            ->groupBy('city_tier')
            ->orderBy('city_tier')
            ->get();

        $ultimoScoring = Customer::max('churn_scored_at');

        return view('admin.dashboard', [
            'total'         => $total,
            'porNivel'      => $porNivel,
            'enRiesgo'      => ($porNivel['alto'] ?? 0) + ($porNivel['medio'] ?? 0),
            'porCategoria'  => $porCategoria,
            'porCiudad'     => $porCiudad,
            'ultimoScoring' => $ultimoScoring,
            'numProductos'  => Product::count(),
            'numOrdenes'    => Order::count(),
        ]);
    }
}
