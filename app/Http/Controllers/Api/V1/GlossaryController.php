<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class GlossaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $path = base_path('docs/api/v1/glossary.json');
        if (! File::isFile($path)) {
            return response()->json(['version' => '0', 'entries' => []]);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return response()->json($payload);
    }
}
