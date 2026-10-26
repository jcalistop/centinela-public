<?php

declare(strict_types=1);

use App\Support\Voyager\BrowseSoporteVoyagerMenu;
use Illuminate\Database\Migrations\Migration;

/**
 * Reaplica `browse_soporte`: sin acceso para `parametrizador` (alineado con la política de Soporte).
 */
return new class extends Migration
{
    public function up(): void
    {
        BrowseSoporteVoyagerMenu::syncRoleGrants();
    }

    public function down(): void
    {
        BrowseSoporteVoyagerMenu::syncRoleGrants();
    }
};
