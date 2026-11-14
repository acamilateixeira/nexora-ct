<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $recebido = (string) $request->headers->get('X-Request-Id', '');

        $id = preg_match('/^[A-Za-z0-9._-]{1,64}$/', $recebido) === 1 ? $recebido : (string) Str::uuid();

        Log::withContext([
            'request_id' => $id,
            'user_id' => optional($request->user())->id,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
