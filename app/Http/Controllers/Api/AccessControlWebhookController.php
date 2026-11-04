<?php

namespace App\Http\Controllers\Api;

use App\Events\AccessControlLogReceived;
use App\Http\Controllers\Controller;
use App\Services\GateTransitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessControlWebhookController extends Controller
{
    public function __invoke(Request $request, GateTransitService $gate): JsonResponse
    {
        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->all();
        }

        if (! is_array($payload)) {
            $payload = ['raw' => $payload];
        }

        $result = $gate->process($payload, $request->ip());

        event(new AccessControlLogReceived($result['log'], [
            'allowed' => $result['allowed'],
            'reason' => $result['reason'],
            'user_name' => $result['user_name'],
            'card_number' => $result['card_number'],
            'course_title' => $result['course_title'],
            'sessions_remaining' => $result['sessions_remaining'],
            'user_id' => $result['user_id'],
            'enrollment_id' => $result['enrollment_id'],
            'course_id' => $result['course_id'],
            'kind' => 'traffic',
        ]));

        return response()->json([
            'message' => $result['allowed'] ? 'تردد مجاز ثبت شد.' : 'تردد غیرمجاز ثبت شد.',
            'id' => $result['log']->id,
            'transit_id' => $result['transit']->id,
            'allowed' => $result['allowed'],
            'reason' => $result['reason'],
            'sessions_remaining' => $result['sessions_remaining'],
        ], 201);
    }
}
