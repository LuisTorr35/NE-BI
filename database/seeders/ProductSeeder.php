<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Catalogo de electro/tecnologia. Categorias mapean a PreferedOrderCat:
        // celulares = Mobile Phone | laptops_accesorios = Laptop & Accessory
        // wearables = Fashion | electro_cocina = Grocery | linea_blanca = Others
        $products = [
            // --- CELULARES Y SMARTPHONES ---
            ['Samsung Galaxy A55 128GB', 'celulares', 'Samsung', 1499.00, 40],
            ['Samsung Galaxy S24 256GB', 'celulares', 'Samsung', 3899.00, 18],
            ['iPhone 15 128GB', 'celulares', 'Apple', 4299.00, 15],
            ['iPhone 15 Pro 256GB', 'celulares', 'Apple', 6199.00, 8],
            ['Xiaomi Redmi Note 13 256GB', 'celulares', 'Xiaomi', 999.00, 60],
            ['Xiaomi 14 512GB', 'celulares', 'Xiaomi', 3299.00, 12],
            ['Motorola Moto G84 256GB', 'celulares', 'Motorola', 1099.00, 35],
            ['Honor X8b 256GB', 'celulares', 'Honor', 899.00, 30],
            ['Cargador rápido USB-C 33W', 'celulares', 'Genérico', 79.00, 120],
            ['Funda protectora antichoque', 'celulares', 'Genérico', 39.00, 200],

            // --- LAPTOPS Y ACCESORIOS ---
            ['Laptop HP 15 Core i5 16GB', 'laptops_accesorios', 'HP', 2799.00, 22],
            ['Laptop Lenovo IdeaPad 3 Ryzen 5', 'laptops_accesorios', 'Lenovo', 2299.00, 28],
            ['Laptop ASUS Vivobook Core i7', 'laptops_accesorios', 'ASUS', 3499.00, 16],
            ['MacBook Air M3 13"', 'laptops_accesorios', 'Apple', 5999.00, 9],
            ['Laptop Gamer Acer Nitro RTX 4050', 'laptops_accesorios', 'Acer', 4899.00, 11],
            ['Monitor LG 24" Full HD', 'laptops_accesorios', 'LG', 549.00, 40],
            ['Teclado mecánico Redragon', 'laptops_accesorios', 'Redragon', 159.00, 80],
            ['Mouse inalámbrico Logitech M280', 'laptops_accesorios', 'Logitech', 69.00, 150],
            ['Disco SSD 1TB NVMe', 'laptops_accesorios', 'Kingston', 299.00, 60],
            ['Mochila para laptop 15.6"', 'laptops_accesorios', 'Genérico', 119.00, 90],

            // --- WEARABLES ---
            ['Apple Watch SE 2', 'wearables', 'Apple', 1299.00, 20],
            ['Samsung Galaxy Watch 6', 'wearables', 'Samsung', 1199.00, 18],
            ['Xiaomi Smart Band 8', 'wearables', 'Xiaomi', 159.00, 100],
            ['Audífonos AirPods Pro 2', 'wearables', 'Apple', 999.00, 25],
            ['Audífonos Sony WH-1000XM5', 'wearables', 'Sony', 1499.00, 14],
            ['Audífonos JBL Tune 520BT', 'wearables', 'JBL', 199.00, 70],
            ['Smartwatch Amazfit Bip 5', 'wearables', 'Amazfit', 279.00, 45],

            // --- ELECTRO DE COCINA ---
            ['Licuadora Oster 1.5L', 'electro_cocina', 'Oster', 189.00, 50],
            ['Air Fryer Imaco 5.5L', 'electro_cocina', 'Imaco', 249.00, 60],
            ['Microondas Samsung 23L', 'electro_cocina', 'Samsung', 399.00, 30],
            ['Hervidor eléctrico 1.7L', 'electro_cocina', 'Electrolux', 89.00, 80],
            ['Cafetera Oster programable', 'electro_cocina', 'Oster', 229.00, 35],
            ['Olla arrocera 1.8L', 'electro_cocina', 'Imaco', 129.00, 55],

            // --- LINEA BLANCA Y OTROS ---
            ['Refrigeradora LG 312L No Frost', 'linea_blanca', 'LG', 1899.00, 10],
            ['Lavadora Samsung 17kg', 'linea_blanca', 'Samsung', 1699.00, 12],
            ['Televisor LG 55" 4K UHD', 'linea_blanca', 'LG', 1799.00, 14],
            ['Televisor Samsung 50" Crystal 4K', 'linea_blanca', 'Samsung', 1599.00, 16],
            ['Cocina a gas Mabe 4 hornillas', 'linea_blanca', 'Mabe', 899.00, 18],
            ['Aspiradora robot Xiaomi', 'linea_blanca', 'Xiaomi', 799.00, 20],
        ];

        DB::table('order_items')->delete();
        DB::table('products')->delete();

        $now = now();
        $rows = [];
        foreach ($products as [$name, $cat, $brand, $price, $stock]) {
            $rows[] = [
                'name'        => $name,
                'slug'        => Str::slug($name),
                'description' => "{$name} - {$brand}. Producto de la categoría " . ucfirst(str_replace('_', ' ', $cat)) . '.',
                'category'    => $cat,
                'brand'       => $brand,
                'price'       => $price,
                'stock'       => $stock,
                'image'       => null,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::table('products')->insert($rows);
        $this->command->info('Productos cargados: ' . count($rows));
    }
}
