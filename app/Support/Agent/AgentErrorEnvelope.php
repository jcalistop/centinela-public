<?php

namespace App\Support\Agent;

use Illuminate\Http\JsonResponse;

final class AgentErrorEnvelope
{
    /**
     * @param  array<string, mixed>|list<mixed>|null  $details
     */
    public static function json(string $code, string $message, int $status, ?array $details = null): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details ?? [],
            ],
        ], $status);
    }
}
