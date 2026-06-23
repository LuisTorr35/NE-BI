<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // --- Identidad (generada con Faker; NO viene en el dataset) ---
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();

            // --- Referencia al dataset ---
            $table->unsignedInteger('customer_code')->unique()->comment('CustomerID original del dataset');
            $table->boolean('is_seeded')->default(false)->comment('true = vino del dataset; false = registro real en la tienda');

            // --- Variable objetivo real del dataset (para entrenar/evaluar) ---
            $table->boolean('actual_churn')->nullable()->comment('Churn real del dataset (1 abandona / 0 permanece)');

            // --- Las 20 variables de comportamiento (universales) ---
            $table->float('tenure')->nullable()->comment('Antiguedad en meses');
            $table->string('preferred_login_device')->nullable();
            $table->unsignedTinyInteger('city_tier')->nullable();
            $table->float('warehouse_to_home')->nullable()->comment('Distancia almacen-hogar');
            $table->string('preferred_payment_mode')->nullable();
            $table->string('gender')->nullable();
            $table->float('hour_spend_on_app')->nullable();
            $table->unsignedTinyInteger('number_of_device_registered')->nullable();
            $table->string('prefered_order_cat')->nullable()->comment('Categoria favorita -> mapea al catalogo');
            $table->unsignedTinyInteger('satisfaction_score')->nullable();
            $table->string('marital_status')->nullable();
            $table->unsignedTinyInteger('number_of_address')->nullable();
            $table->boolean('complain')->nullable()->comment('1 = puso reclamo');
            $table->float('order_amount_hike_from_last_year')->nullable();
            $table->float('coupon_used')->nullable();
            $table->float('order_count')->nullable();
            $table->float('day_since_last_order')->nullable()->comment('Recencia');
            $table->decimal('cashback_amount', 10, 2)->nullable();

            // --- Salida del modelo de churn (la escribe Python) ---
            $table->decimal('churn_probability', 5, 4)->nullable()->comment('0.0000 - 1.0000');
            $table->enum('churn_level', ['alto', 'medio', 'moderado', 'bajo'])->nullable();
            $table->timestamp('churn_scored_at')->nullable();

            $table->timestamps();

            $table->index('churn_probability');
            $table->index('prefered_order_cat');
            $table->index('city_tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
