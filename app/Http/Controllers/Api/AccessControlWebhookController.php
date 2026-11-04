<?php

namespace App\Http\Controllers\Api;

use App\Events\AccessControlLogReceived;
use App\Http\Controllers\Controller;
use App\Models\AccessControlLog;
use App\Support\AccessControlPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessControlWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->all();
        }

        if (! is_array($payload)) {
            $payload = ['raw' => $payload];
        }

        $payload['resolved'] = AccessControlPayload::resolve($payload);

        $log = AccessControlLog::query()->create([
            'payload' => $payload,
            'source_ip' => $request->ip(),
        ]);

        event(new AccessControlLogReceived($log));

        return response()->json([
            'message' => 'لاگ ذخیره شد.',
            'id' => $log->id,
        ], 201);
    }
}
