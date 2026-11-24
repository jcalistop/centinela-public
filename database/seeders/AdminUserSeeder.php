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
            // SDD-338: preferir SS de prueba (install limpia); fallback id 1.
            $dependencyId = (int) (DB::table('cen_hie_dependencies')
                ->where('codigo', 'SS-PRUEBA')
                ->value('id')
                ?? DB::table('cen_hie_dependencies')
                    ->where('nombre', 'SS de prueba')
                    ->value('id')
                ?? 1);

            $adminUser = User::create([
                'role_id' => 1, // ID del rol 'admin'
                'name' => 'Administrador del Sistema',
                'email' => $adminEmail,
                'password' => Hash::make('password'), // Contraseña por defecto
                'cen_hie_dependency_id' => $dependencyId,
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
