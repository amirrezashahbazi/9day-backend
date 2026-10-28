<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessControlLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessControlLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);

        $page = AccessControlLog::query()
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (AccessControlLog $log) => [
                'id' => $log->id,
                'payload' => $log->payload,
                'source_ip' => $log->source_ip,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
