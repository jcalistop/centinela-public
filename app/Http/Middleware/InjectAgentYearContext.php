<?php

namespace App\Http\Middleware;

use App\Models\Year;
use App\Support\Agent\AgentErrorEnvelope;
use App\Support\YearContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectAgentYearContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header('X-Centinela-Year', $request->query('year'));

        if ($raw === null || $raw === '') {
            return $next($request);
        }

        if (! is_numeric($raw)) {
            return AgentErrorEnvelope::json(
                'INVALID_YEAR',
                'El año indicado no es válido.',
                422,
                ['year' => $raw]
            );
        }

        $ano = (int) $raw;
        $year = Year::query()->where('ano', $ano)->first();

        if (! $year instanceof Year) {
            return AgentErrorEnvelope::json(
                'YEAR_NOT_FOUND',
                'No existe el año indicado en el catálogo.',
                422,
                ['year' => $ano]
            );
        }

        $request->attributes->set(YearContext::REQUEST_OVERRIDE_KEY, $year);

        return $next($request);
    }
}
