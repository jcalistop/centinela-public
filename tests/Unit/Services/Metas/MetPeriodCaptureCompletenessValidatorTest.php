<?php

namespace Tests\Unit\Services\Metas;

use App\Models\MetDataField;
use App\Services\Metas\MetPeriodCaptureCompletenessValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetPeriodCaptureCompletenessValidatorTest extends TestCase
{
    use RefreshDatabase;

    private MetPeriodCaptureCompletenessValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(MetPeriodCaptureCompletenessValidator::class);
    }

    /** @test */
    public function periodo_vacio_no_es_incompleto(): void
    {
        $fields = collect([
            $this->makeField(1),
            $this->makeField(2),
        ]);

        $this->assertFalse($this->validator->isPeriodIncomplete([
            1 => null,
            2 => null,
        ], $fields));
    }

    /** @test */
    public function periodo_completo_con_ceros_no_es_incompleto(): void
    {
        $fields = collect([
            $this->makeField(1),
            $this->makeField(2),
        ]);

        $this->assertFalse($this->validator->isPeriodIncomplete([
            1 => 0,
            2 => 0,
        ], $fields));
    }

    /** @test */
    public function periodo_parcial_es_incompleto(): void
    {
        $fields = collect([
            $this->makeField(1),
            $this->makeField(2),
        ]);

        $this->assertTrue($this->validator->isPeriodIncomplete([
            1 => 2,
            2 => null,
        ], $fields));
    }

    /** @test */
    public function validate_manual_rechaza_periodo_parcial(): void
    {
        $fields = collect([
            $this->makeField(1),
            $this->makeField(2),
        ]);

        $message = $this->validator->validateManualPeriod([
            1 => 5,
            2 => null,
        ], $fields);

        $this->assertNotNull($message);
    }

    private function makeField(int $id): MetDataField
    {
        $field = new MetDataField;
        $field->id = $id;

        return $field;
    }
}
