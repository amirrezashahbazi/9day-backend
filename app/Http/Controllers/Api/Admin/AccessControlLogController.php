<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AccessControlLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessControlLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'فقط سوپر ادمین به لاگ کنترل تردد دسترسی دارد.');
        }

        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);

        $page = AccessControlLog::query()
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(function (AccessControlLog $log) {
                $payload = is_array($log->payload) ? $log->payload : [];
                $resolved = is_array($payload['resolved'] ?? null) ? $payload['resolved'] : [];

                return [
                    'id' => $log->id,
                    'payload' => $log->payload,
                    'source_ip' => $log->source_ip,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'card_number' => isset($resolved['card_number']) ? (string) $resolved['card_number'] : null,
                    'user_name' => isset($resolved['user_name']) ? (string) $resolved['user_name'] : null,
                    'kind' => isset($payload['source']) && $payload['source'] === 'set_user_info'
                        ? 'device_command'
                        : 'traffic',
                ];
            }),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
