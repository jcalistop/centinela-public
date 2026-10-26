<?php

namespace Tests\Feature\MetasInstrument;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetStageMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function migraciones_crean_tablas_y_columna_etapa(): void
    {
        $this->assertTrue(Schema::hasTable('met_establishment_responsibles'));
        $this->assertTrue(Schema::hasTable('met_indicator_appeals'));
        $this->assertTrue(Schema::hasTable('met_preliminary_evaluations'));
        $this->assertTrue(Schema::hasTable('met_final_evaluations'));

        $this->assertTrue(Schema::hasColumn('met_results', 'etapa'));
        $this->assertTrue(Schema::hasColumn('met_establishment_results', 'etapa'));
    }
}
