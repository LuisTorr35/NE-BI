<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        [$items, $total] = $this->resolveCart();

        return view('cart.index', compact('items', 'total'));
    }

    public function add(Request $request, Product $product)
    {
        $qty = max(1, (int) $request->input('qty', 1));

        $cart = session()->get('cart', []);
        $cart[$product->id] = ($cart[$product->id] ?? 0) + $qty;
        session()->put('cart', $cart);

        return back()->with('ok', "“{$product->name}” agregado al carrito.");
    }

    public function remove(Product $product)
    {
        $cart = session()->get('cart', []);
        unset($cart[$product->id]);
        session()->put('cart', $cart);

        return back()->with('ok', 'Producto eliminado del carrito.');
    }

    public function clear()
    {
        session()->forget('cart');
        return back()->with('ok', 'Carrito vaciado.');
    }

    /** Devuelve [items, total] a partir del carrito en sesion */
    public static function resolveCart(): array
    {
        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return [collect(), 0.0];
        }

        $products = Product::whereIn('id', array_keys($cart))->get();

        $items = $products->map(function ($p) use ($cart) {
            $qty = $cart[$p->id];
            return [
                'product'  => $p,
                'qty'      => $qty,
                'subtotal' => $qty * (float) $p->price,
            ];
        });

        $total = $items->sum('subtotal');

        return [$items, $total];
    }
}
