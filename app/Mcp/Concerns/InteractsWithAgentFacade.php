<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait InteractsWithAgentFacade
{
    protected function requireAgentUser(): User|Response
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return Response::error('Se requiere autenticación Sanctum (usuario agente).');
        }

        if ($user->cen_hie_dependency_id === null || (int) $user->cen_hie_dependency_id < 1) {
            return Response::error('Usuario agente sin SS fijo.');
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $errorDetails
     */
    protected function applyYearArgument(Request $request, bool $jsonError = false, array $errorDetails = []): Year|Response
    {
        $raw = $request->get('year');
        if ($raw === null || $raw === '') {
            return app(YearContext::class)->resolveDefaultYear();
        }

        if (! is_numeric($raw)) {
            $message = 'El año indicado no es válido.';
            if ($jsonError) {
                return $this->jsonError('VALIDATION_ERROR', $message, array_merge($errorDetails, [
                    'year' => $raw,
                ]));
            }

            return Response::error($message);
        }

        $year = Year::query()->where('ano', (int) $raw)->first();
        if (! $year instanceof Year) {
            $message = 'No existe el año indicado en el catálogo.';
            if ($jsonError) {
                return $this->jsonError('NOT_FOUND', $message, array_merge($errorDetails, [
                    'year' => (int) $raw,
                ]));
            }

            return Response::error($message);
        }

        request()->attributes->set(YearContext::REQUEST_OVERRIDE_KEY, $year);

        return $year;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function jsonOk(array $payload): Response
    {
        return Response::json($payload);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function jsonError(string $code, string $message, array $details = []): Response
    {
        $requestId = request()->attributes->get('agent_request_id');
        if (! is_string($requestId) || $requestId === '') {
            $requestId = (string) Str::uuid();
            request()->attributes->set('agent_request_id', $requestId);
        }

        return Response::json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => $requestId,
            ],
        ]);
    }
}
