<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateForeignKeys extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->addCenCentersForeignKeysForSqlite();

            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `cen_centers`
              ADD CONSTRAINT `cen_centers_cen_hie_dependency_id_foreign`
                FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_centers_cen_type_id_foreign`
                FOREIGN KEY (`cen_type_id`) REFERENCES `cen_types`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_centers_cen_scope_id_foreign`
                FOREIGN KEY (`cen_scope_id`) REFERENCES `cen_scopes`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_centers_cen_certification_id_foreign`
                FOREIGN KEY (`cen_certification_id`) REFERENCES `cen_certifications`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_centers_cen_adm_dependency_id_foreign`
                FOREIGN KEY (`cen_adm_dependency_id`) REFERENCES `cen_adm_dependencies`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_centers_cen_emergency_type_id_foreign`
                FOREIGN KEY (`cen_emergency_type_id`) REFERENCES `cen_emergency_types`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_centers_cen_provider_type_id_foreign`
                FOREIGN KEY (`cen_provider_type_id`) REFERENCES `cen_provider_types`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION;
            SQL);

        DB::statement('ALTER TABLE `cen_addresses` DROP FOREIGN KEY `cen_addresses_cen_center_id_foreign`');
        DB::statement('ALTER TABLE `cen_addresses` DROP FOREIGN KEY `cen_addresses_cen_commune_id_foreign`');
        DB::statement(<<<'SQL'
            ALTER TABLE `cen_addresses`
              ADD CONSTRAINT `cen_addresses_cen_center_id_foreign`
                FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_addresses_cen_commune_id_foreign`
                FOREIGN KEY (`cen_commune_id`) REFERENCES `cen_communes`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION;
            SQL);

        DB::statement('ALTER TABLE `cen_communes` DROP FOREIGN KEY `cen_communes_cen_province_id_foreign`');
        DB::statement(<<<'SQL'
            ALTER TABLE `cen_communes`
              ADD CONSTRAINT `cen_communes_cen_province_id_foreign`
                FOREIGN KEY (`cen_province_id`) REFERENCES `cen_provinces`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION;
            SQL);

        DB::statement('ALTER TABLE `cen_provinces` DROP FOREIGN KEY `cen_provinces_cen_region_id_foreign`');
        DB::statement(<<<'SQL'
            ALTER TABLE `cen_provinces`
              ADD CONSTRAINT `cen_provinces_cen_region_id_foreign`
                FOREIGN KEY (`cen_region_id`) REFERENCES `cen_regions`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION;
            SQL);

        DB::statement('ALTER TABLE `cen_hie_dependencies_regions` DROP FOREIGN KEY `cen_hie_dependencies_regions_cen_hie_dependency_id_foreign`');
        DB::statement('ALTER TABLE `cen_hie_dependencies_regions` DROP FOREIGN KEY `cen_hie_dependencies_regions_cen_region_id_foreign`');
        DB::statement(<<<'SQL'
            ALTER TABLE `cen_hie_dependencies_regions`
              ADD CONSTRAINT `cen_hie_dependencies_regions_cen_hie_dependency_id_foreign`
                FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION,
              ADD CONSTRAINT `cen_hie_dependencies_regions_cen_region_id_foreign`
                FOREIGN KEY (`cen_region_id`) REFERENCES `cen_regions`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION;
            SQL);

        DB::statement('ALTER TABLE `com_indicators` DROP FOREIGN KEY `com_indicators_com_commitment_id_foreign`');
        DB::statement(<<<'SQL'
            ALTER TABLE `com_indicators`
              ADD CONSTRAINT `com_indicators_com_commitment_id_foreign`
                FOREIGN KEY (`com_commitment_id`) REFERENCES `com_commitments`(`id`)
                ON DELETE NO ACTION ON UPDATE NO ACTION;
            SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('cen_centers', function (Blueprint $table) {
                $table->dropForeign(['cen_hie_dependency_id']);
                $table->dropForeign(['cen_type_id']);
                $table->dropForeign(['cen_scope_id']);
                $table->dropForeign(['cen_certification_id']);
                $table->dropForeign(['cen_adm_dependency_id']);
                $table->dropForeign(['cen_emergency_type_id']);
                $table->dropForeign(['cen_provider_type_id']);
            });

            return;
        }

        DB::statement('ALTER TABLE `com_indicators` DROP FOREIGN KEY `com_indicators_com_commitment_id_foreign`');
        DB::statement('ALTER TABLE `cen_hie_dependencies_regions` DROP FOREIGN KEY `cen_hie_dependencies_regions_cen_hie_dependency_id_foreign`');
        DB::statement('ALTER TABLE `cen_hie_dependencies_regions` DROP FOREIGN KEY `cen_hie_dependencies_regions_cen_region_id_foreign`');
        DB::statement('ALTER TABLE `cen_provinces` DROP FOREIGN KEY `cen_provinces_cen_region_id_foreign`');
        DB::statement('ALTER TABLE `cen_communes` DROP FOREIGN KEY `cen_communes_cen_province_id_foreign`');
        DB::statement('ALTER TABLE `cen_addresses` DROP FOREIGN KEY `cen_addresses_cen_center_id_foreign`');
        DB::statement('ALTER TABLE `cen_addresses` DROP FOREIGN KEY `cen_addresses_cen_commune_id_foreign`');
        DB::statement('ALTER TABLE `cen_centers` DROP FOREIGN KEY `cen_centers_cen_hie_dependency_id_foreign`');
        DB::statement('ALTER TABLE `cen_centers` DROP FOREIGN KEY `cen_centers_cen_type_id_foreign`');
        DB::statement('ALTER TABLE `cen_centers` DROP FOREIGN KEY `cen_centers_cen_scope_id_foreign`');
        DB::statement('ALTER TABLE `cen_centers` DROP FOREIGN KEY `cen_centers_cen_certification_id_foreign`');
        DB::statement('ALTER TABLE `cen_centers` DROP FOREIGN KEY `cen_centers_cen_adm_dependency_id_foreign`');
        DB::statement('ALTER TABLE `cen_centers` DROP FOREIGN KEY `cen_centers_cen_emergency_type_id_foreign`');
        DB::statement('ALTER TABLE `cen_centers` DROP FOREIGN KEY `cen_centers_cen_provider_type_id_foreign`');
    }

    private function addCenCentersForeignKeysForSqlite(): void
    {
        Schema::table('cen_centers', function (Blueprint $table) {
            $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_type_id')->references('id')->on('cen_types')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_scope_id')->references('id')->on('cen_scopes')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_certification_id')->references('id')->on('cen_certifications')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_adm_dependency_id')->references('id')->on('cen_adm_dependencies')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_emergency_type_id')->references('id')->on('cen_emergency_types')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_provider_type_id')->references('id')->on('cen_provider_types')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }
}
