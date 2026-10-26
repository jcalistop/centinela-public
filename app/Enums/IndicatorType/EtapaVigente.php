<?php

namespace App\Enums\IndicatorType;

enum EtapaVigente: string
{
    case NoIniciado = 'NO_INICIADO';
    case Formulacion = 'FORMULACION';
    case Autoevaluacion = 'AUTOEVALUACION';
    case Preliminar = 'PRELIMINAR';
    case Apelacion = 'APELACION';
    case Final = 'FINAL';
    case Cerrado = 'CERRADO';

    public function label(): string
    {
        return match ($this) {
            self::NoIniciado => 'No iniciado',
            self::Formulacion => 'Formulación',
            self::Autoevaluacion => 'Autoevaluación',
            self::Preliminar => 'Evaluación preliminar',
            self::Apelacion => 'Apelación',
            self::Final => 'Evaluación final',
            self::Cerrado => 'Cerrado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NoIniciado => 'Referentes y visualizadores del SS no acceden al instrumento. Control de gestión prepara el ciclo.',
            self::Formulacion => 'Los responsables de los establecimientos definen convenio y ponderaciones; los referentes y control de gestión ya pueden cargar datos.',
            self::Autoevaluacion => 'Carga de datos con el convenio congelado. Los referentes operan en indicadores aplicables.',
            self::Preliminar => 'Control-gestión carga datos desde evaluación externa (Minsal, SEREMI, DIPRES, etc).',
            self::Apelacion => 'Los responsables registran apelaciones de los indicadores en convenio.',
            self::Final => 'Se resuelven apelaciones desde Minsal y control de gestión registra datos.',
            self::Cerrado => 'El ciclo terminó. Todo queda en solo lectura para todos los roles.',
        };
    }

    /**
     * @return list<self>
     */
    public static function ordenadasPorCiclo(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b): int => $a->ordenCiclo() <=> $b->ordenCiclo());

        return $cases;
    }

    public function ordenCiclo(): int
    {
        return match ($this) {
            self::NoIniciado => 0,
            self::Formulacion => 1,
            self::Autoevaluacion => 2,
            self::Preliminar => 3,
            self::Apelacion => 4,
            self::Final => 5,
            self::Cerrado => 6,
        };
    }

    public function esRetrocesoHacia(self $destino): bool
    {
        return $destino->ordenCiclo() < $this->ordenCiclo();
    }

    public function habilitaAccesoReferente(): bool
    {
        return $this !== self::NoIniciado;
    }

    public function permiteFormulacionConvenio(): bool
    {
        return $this === self::Formulacion;
    }

    public function permiteConfiguracionEstructural(): bool
    {
        return $this !== self::NoIniciado && $this !== self::Cerrado;
    }

    public function permiteCargaMensual(): bool
    {
        return $this === self::Formulacion || $this === self::Autoevaluacion;
    }
}
