<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Categoria del catalogo (mapea a PreferedOrderCat del dataset)
            $table->enum('category', [
                'laptops_accesorios',   // Laptop & Accessory
                'celulares',            // Mobile Phone / Mobile
                'wearables',            // Fashion
                'electro_cocina',       // Grocery
                'linea_blanca',         // Others
            ]);
            $table->string('brand')->nullable();

            $table->decimal('price', 10, 2)->comment('Precio en soles');
            $table->unsignedInteger('stock')->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
