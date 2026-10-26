<?php

namespace Tests\Unit\Services\Metas;

use App\Models\MetDataField;
use App\Services\Metas\MetPeriodCaptureXlsxNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetPeriodCaptureXlsxNormalizerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function normaliza_ceros_cuando_hay_al_menos_un_valor(): void
    {
        $fields = collect([
            $this->makeField(1),
            $this->makeField(2),
        ]);

        $normalized = app(MetPeriodCaptureXlsxNormalizer::class)->normalizeImportedPeriodValues($fields, [
            1 => 2.0,
            2 => null,
        ]);

        $this->assertSame([
            1 => 2.0,
            2 => 0.0,
        ], $normalized);
    }

    /** @test */
    public function no_normaliza_cuando_todo_vacio_en_archivo(): void
    {
        $fields = collect([
            $this->makeField(1),
            $this->makeField(2),
        ]);

        $normalized = app(MetPeriodCaptureXlsxNormalizer::class)->normalizeImportedPeriodValues($fields, [
            1 => null,
            2 => null,
        ]);

        $this->assertSame([], $normalized);
    }

    private function makeField(int $id): MetDataField
    {
        $field = new MetDataField;
        $field->id = $id;

        return $field;
    }
}
