<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index()
    {
        [$items, $total] = CartController::resolveCart();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('ok', 'Tu carrito está vacío.');
        }

        return view('checkout.index', compact('items', 'total'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_mode' => ['required', 'in:tarjeta_debito,tarjeta_credito,billetera_digital,contra_entrega'],
        ]);

        [$items, $total] = CartController::resolveCart();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('ok', 'Tu carrito está vacío.');
        }

        $customer = Auth::guard('customer')->user();

        $order = DB::transaction(function () use ($items, $total, $data, $customer) {
            $order = Order::create([
                'customer_id'  => $customer->id,
                'total'        => $total,
                'payment_mode' => $data['payment_mode'],
                'status'       => 'pagado',
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity'   => $item['qty'],
                    'unit_price' => $item['product']->price,
                ]);
                $item['product']->decrement('stock', $item['qty']);
            }

            return $order;
        });

        session()->forget('cart');

        return redirect()->route('catalog.index')
            ->with('ok', "¡Compra realizada! Pedido #{$order->id} por S/ " . number_format($total, 2));
    }
}
