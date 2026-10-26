<?php

namespace App\Support\Comges;

use App\Enums\IndicatorType\EtapaVigente;
use App\Helpers\Helper;
use App\Models\IndicatorTypeSetting;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\Auth;

final readonly class ComgesBrowseContext
{
    public function __construct(
        public string $search,
        public string $ambitoFilter,
        public ?int $yearId,
        public ?int $yearAno,
        public bool $useScoreScale,
        public ?int $cenHieDependencyId,
        public ?string $userRoleName,
        public ?EtapaVigente $comgesEtapaVigente,
        public ?User $user,
        public ?Year $year,
    ) {}

    public static function fromSession(string $search, string $ambitoFilter = ''): self
    {
        $year = Helper::showCurrentYear();
        $user = Auth::user();
        $useScoreScale = ComgesYearMode::usesScoreScale($year);
        $comgesEtapaVigente = null;

        if ($useScoreScale && $user instanceof User) {
            $setting = IndicatorTypeSetting::getData(1);
            $comgesEtapaVigente = $setting?->etapa_vigente;
        }

        return new self(
            search: trim($search),
            ambitoFilter: trim($ambitoFilter),
            yearId: $year instanceof Year ? (int) $year->id : null,
            yearAno: $year instanceof Year ? (int) $year->ano : null,
            useScoreScale: $useScoreScale,
            cenHieDependencyId: $user?->cen_hie_dependency_id,
            userRoleName: $user?->role?->name,
            comgesEtapaVigente: $comgesEtapaVigente,
            user: $user instanceof User ? $user : null,
            year: $year instanceof Year ? $year : null,
        );
    }

    public function isSearching(): bool
    {
        return $this->search !== '';
    }

    public function hasAmbitoFilter(): bool
    {
        return $this->ambitoFilter !== '';
    }

    public function isFiltering(): bool
    {
        return $this->isSearching() || $this->hasAmbitoFilter();
    }

    public function showsEmptyCommitments(): bool
    {
        return in_array($this->userRoleName, ['parametrizador', 'admin', 'control-gestion'], true);
    }
}
