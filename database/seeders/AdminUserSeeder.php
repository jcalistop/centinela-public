<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea un usuario administrador y le asigna el rol correspondiente.
     */
    public function run(): void
    {
        // Verificar si ya existe un usuario con el correo de administrador
        $adminEmail = 'admin@centinela.cl';
        $existingUser = User::where('email', $adminEmail)->first();

        if (! $existingUser) {
            // Crear usuario administrador
            $adminUser = User::create([
                'role_id' => 1, // ID del rol 'admin'
                'name' => 'Administrador del Sistema',
                'email' => $adminEmail,
                'password' => Hash::make('password'), // Contraseña por defecto
                'cen_hie_dependency_id' => 1, // Dependencia por defecto
                'email_verified_at' => now(),
            ]);

            // Agregar relación en la tabla user_roles
            DB::table('user_roles')->insert([
                'user_id' => $adminUser->id,
                'role_id' => 1,
            ]);

            $this->command->info('Usuario administrador creado exitosamente.');
        } else {
            $this->command->info('El usuario administrador ya existe.');
        }
    }
}
