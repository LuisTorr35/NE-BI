<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RiesgoController extends Controller
{
    public function index(Request $request)
    {
        $nivel    = $request->query('nivel');
        $categoria = $request->query('cat');
        $search   = $request->query('q');

        $clientes = Customer::query()
            ->whereNotNull('churn_probability')
            ->when($nivel, fn ($q) => $q->where('churn_level', $nivel))
            ->when($categoria, fn ($q) => $q->where('prefered_order_cat', $categoria))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when(!$nivel, fn ($q) => $q->whereIn('churn_level', ['alto', 'medio', 'moderado']))
            ->orderByDesc('churn_probability')
            ->paginate(20)
            ->withQueryString();

        return view('admin.riesgo.index', [
            'clientes'   => $clientes,
            'nivel'      => $nivel,
            'categoria'  => $categoria,
            'search'     => $search,
            'categorias' => Customer::whereNotNull('prefered_order_cat')
                                ->distinct()->pluck('prefered_order_cat'),
        ]);
    }

    public function show(Customer $customer)
    {
        // Productos sugeridos de su categoria favorita (para la accion de retencion)
        $catMap = [
            'Mobile Phone'       => 'celulares',
            'Laptop & Accessory' => 'laptops_accesorios',
            'Fashion'            => 'wearables',
            'Grocery'            => 'electro_cocina',
            'Others'             => 'linea_blanca',
        ];
        $catTienda = $catMap[$customer->prefered_order_cat] ?? null;
        $sugeridos = $catTienda
            ? Product::where('category', $catTienda)->limit(3)->get()
            : collect();

        return view('admin.riesgo.show', compact('customer', 'sugeridos'));
    }

    /** Re-evalua al cliente EN VIVO llamando al servicio FastAPI del modelo. */
    public function evaluar(Customer $customer)
    {
        $url = rtrim(env('CHURN_API_URL', 'http://127.0.0.1:9000'), '/') . '/predict';

        try {
            $resp = Http::timeout(5)->post($url, [
                'tenure'                           => $customer->tenure,
                'preferred_login_device'           => $customer->preferred_login_device,
                'city_tier'                        => $customer->city_tier,
                'warehouse_to_home'                => $customer->warehouse_to_home,
                'preferred_payment_mode'           => $customer->preferred_payment_mode,
                'gender'                           => $customer->gender,
                'hour_spend_on_app'                => $customer->hour_spend_on_app,
                'number_of_device_registered'      => $customer->number_of_device_registered,
                'prefered_order_cat'               => $customer->prefered_order_cat,
                'satisfaction_score'               => $customer->satisfaction_score,
                'marital_status'                   => $customer->marital_status,
                'number_of_address'                => $customer->number_of_address,
                'complain'                         => $customer->complain ? 1 : 0,
                'order_amount_hike_from_last_year' => $customer->order_amount_hike_from_last_year,
                'coupon_used'                      => $customer->coupon_used,
                'order_count'                      => $customer->order_count,
                'day_since_last_order'             => $customer->day_since_last_order,
                'cashback_amount'                  => $customer->cashback_amount,
            ]);

            if ($resp->failed()) {
                return back()->with('error', 'El servicio de predicción respondió con error.');
            }

            $data = $resp->json();
            $customer->update([
                'churn_probability' => $data['churn_probability'],
                'churn_level'       => $data['churn_level'],
                'churn_scored_at'   => now(),
            ]);

            return back()->with('ok', "Cliente re-evaluado en vivo: {$data['churn_level']} ("
                . round($data['churn_probability'] * 100, 1) . '%).');

        } catch (\Throwable $e) {
            return back()->with('error',
                'No se pudo conectar al servicio de predicción (¿está corriendo uvicorn en el puerto 9000?).');
        }
    }
}
