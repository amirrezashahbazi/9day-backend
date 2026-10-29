<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAccessControlWebhookKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.access_control_webhook.key');
        $provided = (string) (
            $request->header('X-Access-Control-Key')
            ?: $request->bearerToken()
            ?: $request->query('key')
            ?: ''
        );

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'کلید وبهوک نامعتبر است.',
            ], 401);
        }

        return $next($request);
    }
}
