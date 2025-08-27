<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tus seeders existentes
        $this->call(PaisesYProvinciasSeeder::class);
        $this->call(PoblacionesSeeder::class);

        // Nuevos
        $this->call(PoliciesShieldStyleSeeder::class);
        $this->call(UsersSeeder::class);
    }
}
