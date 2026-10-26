<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-192: logo opcional por Servicio de Salud + campo BREAD Voyager.
 */
return new class extends Migration
{
    private const TABLE = 'cen_hie_dependencies';

    private const BREAD_SLUG = 'servicios-de-salud';

    private const FIELD = 'logo';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE) && ! Schema::hasColumn(self::TABLE, self::FIELD)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->string(self::FIELD)->nullable()->after('habilitado');
            });
        }

        $this->ensureBreadRow();
    }

    public function down(): void
    {
        if (Schema::hasTable('data_types') && Schema::hasTable('data_rows')) {
            $dataTypeId = DB::table('data_types')->where('slug', self::BREAD_SLUG)->value('id');
            if ($dataTypeId) {
                DB::table('data_rows')
                    ->where('data_type_id', $dataTypeId)
                    ->where('field', self::FIELD)
                    ->delete();
            }
        }

        if (Schema::hasTable(self::TABLE) && Schema::hasColumn(self::TABLE, self::FIELD)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropColumn(self::FIELD);
            });
        }
    }

    private function ensureBreadRow(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasTable('data_rows')) {
            return;
        }

        $dataTypeId = DB::table('data_types')->where('slug', self::BREAD_SLUG)->value('id');
        if (! $dataTypeId) {
            return;
        }

        $exists = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', self::FIELD)
            ->exists();

        $details = json_encode([
            'quality' => '90%',
            'upsize' => true,
            'resize' => [
                'width' => '800',
            ],
            'view_browse' => 'voyager::servicios-de-salud.partials.browse-logo',
            'view_edit' => 'voyager::servicios-de-salud.partials.edit-logo',
            'view_add' => 'voyager::servicios-de-salud.partials.edit-logo',
        ], JSON_THROW_ON_ERROR);

        if ($exists) {
            DB::table('data_rows')
                ->where('data_type_id', $dataTypeId)
                ->where('field', self::FIELD)
                ->update([
                    'type' => 'image',
                    'display_name' => 'Logo',
                    'browse' => 1,
                    'read' => 1,
                    'edit' => 1,
                    'add' => 1,
                    'delete' => 1,
                    'details' => $details,
                ]);

            return;
        }

        $habilitadoOrder = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'habilitado')
            ->value('order');

        $order = $habilitadoOrder !== null
            ? ((int) $habilitadoOrder) + 1
            : ((int) DB::table('data_rows')->where('data_type_id', $dataTypeId)->max('order') + 1);

        DB::table('data_rows')->insert([
            'data_type_id' => $dataTypeId,
            'field' => self::FIELD,
            'type' => 'image',
            'display_name' => 'Logo',
            'required' => 0,
            'browse' => 1,
            'read' => 1,
            'edit' => 1,
            'add' => 1,
            'delete' => 1,
            'details' => $details,
            'order' => $order,
        ]);
    }
};
