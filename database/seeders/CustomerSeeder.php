<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class CustomerSeeder extends Seeder
{
    /**
     * Carga los 5,630 clientes del dataset (CSV) y les genera identidad
     * (nombre, email, password) con Faker. El comportamiento viene del dataset;
     * la identidad NO existe en el dataset y se sintetiza aqui.
     */
    public function run(): void
    {
        $path = database_path('data/customers.csv');

        if (! file_exists($path)) {
            $this->command->error("No se encontro el CSV en {$path}");
            return;
        }

        $faker = Faker::create('es_PE');

        // Hash una sola vez (bcrypt es caro); todos los clientes del dataset
        // comparten la clave "password" para poder demostrar el login.
        $sharedPassword = Hash::make('password');

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle); // descarta encabezado

        // Helpers para limpiar valores faltantes del CSV
        $num = fn ($v) => ($v === '' || $v === null) ? null : (float) $v;
        $int = fn ($v) => ($v === '' || $v === null) ? null : (int) $v;

        $batch = [];
        $count = 0;
        $now = now();

        DB::table('customers')->delete();

        while (($row = fgetcsv($handle)) !== false) {
            [
                $customerId, $churn, $tenure, $loginDevice, $cityTier, $warehouseToHome,
                $paymentMode, $gender, $hourOnApp, $numDevices, $orderCat, $satisfaction,
                $marital, $numAddress, $complain, $amountHike, $couponUsed, $orderCount,
                $daySinceLast, $cashback,
            ] = $row;

            // firstName + lastName evita titulos/sufijos ("Lic.", "Hijo", "Sr.")
            $name      = $faker->firstName() . ' ' . $faker->lastName();
            $slugName  = Str::slug($name, '.');
            $email     = "{$slugName}.{$customerId}@correo.com";

            $batch[] = [
                'name'         => $name,
                'email'        => $email,
                'password'     => $sharedPassword,
                'customer_code'=> (int) $customerId,
                'is_seeded'    => true,
                'actual_churn' => (int) $churn,

                'tenure'                           => $num($tenure),
                'preferred_login_device'           => $loginDevice ?: null,
                'city_tier'                        => $int($cityTier),
                'warehouse_to_home'                => $num($warehouseToHome),
                'preferred_payment_mode'           => $paymentMode ?: null,
                'gender'                           => $gender ?: null,
                'hour_spend_on_app'                => $num($hourOnApp),
                'number_of_device_registered'      => $int($numDevices),
                'prefered_order_cat'               => $orderCat ?: null,
                'satisfaction_score'               => $int($satisfaction),
                'marital_status'                   => $marital ?: null,
                'number_of_address'                => $int($numAddress),
                'complain'                         => $int($complain),
                'order_amount_hike_from_last_year' => $num($amountHike),
                'coupon_used'                      => $num($couponUsed),
                'order_count'                      => $num($orderCount),
                'day_since_last_order'             => $num($daySinceLast),
                'cashback_amount'                  => $num($cashback),

                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            $count++;

            if (count($batch) >= 500) {
                DB::table('customers')->insert($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('customers')->insert($batch);
        }

        fclose($handle);

        $this->command->info("Clientes cargados: {$count}");
    }
}
