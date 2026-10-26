<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agent\ComgesReadAdapter;
use App\Services\Agent\MetasReadAdapter;
use Illuminate\Http\JsonResponse;

class InstrumentosController extends Controller
{
    public function __invoke(MetasReadAdapter $metas): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'code' => ComgesReadAdapter::INSTRUMENTO,
                    'label' => 'Compromisos de Gestión (COMGES)',
                    'leyes' => [],
                ],
                [
                    'code' => MetasReadAdapter::INSTRUMENTO,
                    'label' => 'Metas sanitarias',
                    'leyes' => $metas->availableLeyes()->all(),
                ],
            ],
        ]);
    }
}
