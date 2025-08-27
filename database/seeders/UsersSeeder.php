<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Usuarios demo (cambia emails y nombres a tu gusto)
        $users = [
            [
                'name' => 'Sara',
                'apellidos' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'rol' => 'superadmin',
                'proveedor_id' => null,
            ],
            [
                'name' => 'Alberto',
                'apellidos' => 'Gestión',
                'email' => 'administracion@example.com',
                'password' => Hash::make('password'),
                'rol' => 'administración',
                'proveedor_id' => null,
            ],
            [
                'name' => 'Teo',
                'apellidos' => 'Tecnico',
                'email' => 'tecnico@example.com',
                'password' => Hash::make('password'),
                'rol' => 'técnico',
                'proveedor_id' => null,
            ],
            [
                'name' => 'Nora',
                'apellidos' => 'Trasportes',
                'email' => 'transportista@example.com',
                'password' => Hash::make('password'),
                'rol' => 'transportista',
                // si usas user->proveedor_id, pon aquí un id válido o déjalo null
                'proveedor_id' => null,
            ],
        ];

        foreach ($users as $u) {
            /** @var \App\Models\User $user */
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'apellidos' => $u['apellidos'],
                    'password' => $u['password'],
                    'proveedor_id' => $u['proveedor_id'] ?? null,
                ]
            );

            // Asigna rol (spatie/permission)
            if (!$user->hasRole($u['rol'])) {
                $user->assignRole($u['rol']);
            }
        }
    }
}
