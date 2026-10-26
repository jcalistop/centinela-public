<?php

namespace App\Support\Agent;

use App\Models\User;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Http\Request;

final class AgentRequestContext
{
    public function __construct(private YearContext $yearContext) {}

    public function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Usuario agente requerido.');
        }

        return $user;
    }

    public function ssId(Request $request): int
    {
        $ssId = $this->user($request)->cen_hie_dependency_id;
        if ($ssId === null || $ssId < 1) {
            throw new \RuntimeException('Usuario agente sin SS fijo.');
        }

        return (int) $ssId;
    }

    public function year(): Year
    {
        $year = $this->yearContext->current();
        if ($year instanceof Year) {
            return $year;
        }

        return $this->yearContext->resolveDefaultYear();
    }
}
