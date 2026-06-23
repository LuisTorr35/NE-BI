<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('cat');
        $search   = $request->query('q');

        $products = Product::query()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('catalog.index', [
            'products'   => $products,
            'categories' => Product::CATEGORIES,
            'category'   => $category,
            'search'     => $search,
        ]);
    }

    public function show(Product $product)
    {
        $related = Product::where('category', $product->category)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();

        return view('catalog.show', compact('product', 'related'));
    }
}
