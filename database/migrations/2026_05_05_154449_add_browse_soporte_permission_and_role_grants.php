<?php

declare(strict_types=1);

use App\Support\Voyager\BrowseSoporteVoyagerMenu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Permiso Voyager para el ítem Soporte (`browse_soporte`). La tabla `menus`/`menu_items` no guarda ACL;
 * Voyager usa `MenuItemPolicy` con el slug `soporte` → permiso `browse_soporte`.
 */
return new class extends Migration
{
    public function up(): void
    {
        BrowseSoporteVoyagerMenu::syncRoleGrants();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'browse_soporte')->value('id');
        if ($permissionId === null) {
            return;
        }
        DB::table('permission_role')->where('permission_id', (int) $permissionId)->delete();
        DB::table('permissions')->where('id', (int) $permissionId)->delete();
        Cache::forget('voyager_menu_admin');
    }
};
