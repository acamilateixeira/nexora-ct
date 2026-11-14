<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::connection()->select('select 1');
        } catch (Throwable $e) {
            Log::error('readiness.database_unavailable', ['exception' => get_class($e)]);

            return response()->json(['status' => 'unavailable', 'checks' => ['database' => 'fail']], 503);
        }

        return response()->json(['status' => 'ok', 'checks' => ['database' => 'ok']]);
    }
}
