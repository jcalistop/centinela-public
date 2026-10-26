<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Agent\AgentErrorEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $path = base_path('docs/api/v1/openapi.json');
        if (! File::isFile($path)) {
            return AgentErrorEnvelope::json('NOT_FOUND', 'OpenAPI no publicado.', 404);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return response()->json($payload);
    }
}
