<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        DB::table('roles')->insert([
            ['name' => 'superadmin', 'guard_name' => 'web'],
            ['name' => 'administración', 'guard_name' => 'web'],
            ['name' => 'operarios', 'guard_name' => 'web'],
            ['name' => 'técnico', 'guard_name' => 'web'],
            ['name' => 'taller', 'guard_name' => 'web'],
            ['name' => 'proveedor de biomasa/madera', 'guard_name' => 'web'],
            ['name' => 'proveedor de servicio', 'guard_name' => 'web'],
            ['name' => 'transportista', 'guard_name' => 'web'],
        ]);
    }
}
