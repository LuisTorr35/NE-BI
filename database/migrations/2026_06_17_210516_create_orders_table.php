<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->decimal('total', 10, 2)->default(0);
            $table->enum('payment_mode', [
                'tarjeta_debito',
                'tarjeta_credito',
                'billetera_digital',  // Yape / Plin
                'contra_entrega',
            ]);
            $table->enum('status', ['pendiente', 'pagado', 'enviado', 'entregado', 'cancelado'])
                ->default('pendiente');

            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
