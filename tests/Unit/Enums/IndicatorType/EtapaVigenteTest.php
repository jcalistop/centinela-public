<?php

namespace Tests\Unit\Enums\IndicatorType;

use App\Enums\IndicatorType\EtapaVigente;
use PHPUnit\Framework\TestCase;

class EtapaVigenteTest extends TestCase
{
    public function test_no_incluye_etapa_anual(): void
    {
        $values = array_map(fn (EtapaVigente $case) => $case->value, EtapaVigente::cases());

        $this->assertNotContains('ANUAL', $values);
        $this->assertContains('CERRADO', $values);
        $this->assertContains('NO_INICIADO', $values);
        $this->assertContains('FORMULACION', $values);
    }

    public function test_label_y_description_no_vacios_en_todos_los_cases(): void
    {
        foreach (EtapaVigente::cases() as $case) {
            $this->assertNotSame('', trim($case->label()), "label vacío para {$case->value}");
            $this->assertNotSame('', trim($case->description()), "description vacía para {$case->value}");
        }
    }

    public function test_label_para_cerrado(): void
    {
        $this->assertSame('Cerrado', EtapaVigente::Cerrado->label());
    }

    public function test_ordenadas_por_ciclo_lista_cerrado_al_final(): void
    {
        $ordenadas = EtapaVigente::ordenadasPorCiclo();

        $this->assertSame(EtapaVigente::NoIniciado, $ordenadas[0]);
        $this->assertSame(EtapaVigente::Formulacion, $ordenadas[1]);
        $this->assertSame(EtapaVigente::Autoevaluacion, $ordenadas[2]);
        $this->assertSame(EtapaVigente::Cerrado, $ordenadas[array_key_last($ordenadas)]);
    }

    public function test_helpers_de_ciclo_metas(): void
    {
        $this->assertFalse(EtapaVigente::NoIniciado->habilitaAccesoReferente());
        $this->assertTrue(EtapaVigente::Formulacion->habilitaAccesoReferente());
        $this->assertTrue(EtapaVigente::Formulacion->permiteFormulacionConvenio());
        $this->assertFalse(EtapaVigente::Autoevaluacion->permiteFormulacionConvenio());
        $this->assertTrue(EtapaVigente::Autoevaluacion->permiteConfiguracionEstructural());
        $this->assertFalse(EtapaVigente::NoIniciado->permiteConfiguracionEstructural());
        $this->assertTrue(EtapaVigente::Formulacion->permiteCargaMensual());
        $this->assertTrue(EtapaVigente::Autoevaluacion->esRetrocesoHacia(EtapaVigente::Formulacion));
    }
}
