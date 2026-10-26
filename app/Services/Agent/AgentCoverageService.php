<?php

namespace App\Services\Agent;

use App\Models\Year;

class AgentCoverageService
{
    public function __construct(
        private ComgesReadAdapter $comges,
        private MetasReadAdapter $metas,
    ) {}

    /**
     * Matriz de cobertura del catálogo del año: instrumento × ley × indicadores.
     *
     * Cada combinación se informa siempre, incluso con 0 indicadores: el vacío es
     * un hecho explícito y no una fila omitida.
     *
     * @return list<array{instrumento: string, ley: string|null, indicadores_total: int}>
     */
    public function forYear(Year $year): array
    {
        $rows = [[
            'instrumento' => ComgesReadAdapter::INSTRUMENTO,
            'ley' => null,
            'indicadores_total' => $this->comges->countIndicators($year),
        ]];

        foreach ($this->metas->availableLeyes() as $ley) {
            $rows[] = [
                'instrumento' => MetasReadAdapter::INSTRUMENTO,
                'ley' => $ley['code'],
                'indicadores_total' => $this->metas->countIndicatorsForLey($year, $ley['code']),
            ];
        }

        return $rows;
    }
}
