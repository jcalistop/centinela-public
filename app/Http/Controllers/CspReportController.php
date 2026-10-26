<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Log::channel('csp')->info('csp-report', [
            'report' => $request->all(),
        ]);

        return response()->noContent();
    }
}
