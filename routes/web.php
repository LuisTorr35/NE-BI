<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\ChatbotController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RiesgoController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

// --- Tienda (storefront) ---
Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/producto/{product}', [CatalogController::class, 'show'])->name('catalog.show');

// --- Carrito ---
Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrito/{product}', [CartController::class, 'add'])->name('cart.add');
Route::delete('/carrito/{product}', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/carrito', [CartController::class, 'clear'])->name('cart.clear');

// --- Autenticacion de clientes ---
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->name('login.attempt');
});
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');

// --- Checkout (requiere cliente autenticado) ---
Route::middleware('auth:customer')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
});

// --- Panel de administración + Business Intelligence ---
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Pestaña BI: clientes en riesgo
        Route::get('/riesgo', [RiesgoController::class, 'index'])->name('riesgo.index');
        Route::get('/cliente/{customer}', [RiesgoController::class, 'show'])->name('riesgo.show');
        Route::post('/cliente/{customer}/evaluar', [RiesgoController::class, 'evaluar'])->name('riesgo.evaluar');

        // Asistente BI (chatbot Groq/Llama)
        Route::get('/asistente', [ChatbotController::class, 'index'])->name('asistente.index');
        Route::post('/asistente/preguntar', [ChatbotController::class, 'preguntar'])->name('asistente.preguntar');
        Route::post('/asistente/limpiar', [ChatbotController::class, 'limpiar'])->name('asistente.limpiar');
    });
});
