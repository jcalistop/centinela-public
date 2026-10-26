<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SDD-023 — Limpieza de BD tras deprecación del módulo invitado (v4.2.0).
 *
 * Elimina el rol `invitado`, el usuario técnico asociado y sus pivotes Voyager.
 * Idempotente: no-op si el rol ya no existe.
 */
return new class extends Migration
{
    private const GUEST_USER_EMAIL = 'invitado@centinela.local';

    public function up(): void
    {
        $invitadoRoleId = DB::table('roles')->where('name', 'invitado')->value('id');
        if (! $invitadoRoleId) {
            return;
        }

        $invitadoRoleId = (int) $invitadoRoleId;

        $guestUserIds = DB::table('users')
            ->where(function ($query) use ($invitadoRoleId): void {
                $query->where('role_id', $invitadoRoleId)
                    ->orWhere('email', self::GUEST_USER_EMAIL);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($guestUserIds !== []) {
            DB::table('user_roles')->whereIn('user_id', $guestUserIds)->delete();
            DB::table('users')->whereIn('id', $guestUserIds)->delete();
        }

        DB::table('user_roles')->where('role_id', $invitadoRoleId)->delete();
        DB::table('permission_role')->where('role_id', $invitadoRoleId)->delete();
        DB::table('roles')->where('id', $invitadoRoleId)->delete();
    }

    public function down(): void
    {
        $now = now();

        $invitadoRoleId = DB::table('roles')->where('name', 'invitado')->value('id');
        if (! $invitadoRoleId) {
            $invitadoRoleId = DB::table('roles')->insertGetId([
                'name' => 'invitado',
                'display_name' => 'Invitado',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $invitadoRoleId = (int) $invitadoRoleId;

        $visualizadorRoleId = DB::table('roles')->where('name', 'visualizador')->value('id');
        if ($visualizadorRoleId) {
            $permissionIds = DB::table('permission_role')
                ->where('role_id', (int) $visualizadorRoleId)
                ->pluck('permission_id');

            $rows = $permissionIds->map(fn ($pid): array => [
                'permission_id' => (int) $pid,
                'role_id' => $invitadoRoleId,
            ])->all();

            if ($rows !== []) {
                DB::table('permission_role')->insertOrIgnore($rows);
            }
        }

        $cenHieDependencyId = (int) (DB::table('cen_hie_dependencies')->orderBy('id')->value('id') ?? 1);

        $existingUserId = DB::table('users')->where('email', self::GUEST_USER_EMAIL)->value('id');
        if (! $existingUserId) {
            $userId = DB::table('users')->insertGetId([
                'role_id' => $invitadoRoleId,
                'name' => 'Usuario invitado',
                'email' => self::GUEST_USER_EMAIL,
                'password' => Hash::make(Str::random(64)),
                'cen_hie_dependency_id' => $cenHieDependencyId,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $userId = (int) $existingUserId;
            DB::table('users')->where('id', $userId)->update([
                'role_id' => $invitadoRoleId,
                'updated_at' => $now,
            ]);
        }

        DB::table('user_roles')->insertOrIgnore([
            'user_id' => $userId,
            'role_id' => $invitadoRoleId,
        ]);
    }
};
