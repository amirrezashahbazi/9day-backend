<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkIdsRequest;
use App\Http\Requests\Admin\IndexTimeSlotsRequest;
use App\Http\Requests\Admin\TimeSlotRequest;
use App\Http\Resources\TimeSlotResource;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;

class TimeSlotController extends Controller
{
    public function index(IndexTimeSlotsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $slots = TimeSlot::query()
            ->when(filled($filters['period'] ?? null), fn ($query) => $query->where('period', $filters['period']))
            ->when(filled($filters['gender'] ?? null), fn ($query) => $query->where('gender', $filters['gender']))
            ->orderBy('starts_at')
            ->orderBy('gender')
            ->get();

        return response()->json([
            'data' => TimeSlotResource::collection($slots)->resolve(),
        ]);
    }

    public function store(TimeSlotRequest $request): JsonResponse
    {
        $slot = TimeSlot::query()->create($request->validated());

        return response()->json([
            'message' => 'سانس ثبت شد.',
            'time_slot' => new TimeSlotResource($slot),
        ], 201);
    }

    public function update(TimeSlotRequest $request, TimeSlot $timeSlot): JsonResponse
    {
        $timeSlot->update($request->validated());

        return response()->json([
            'message' => 'سانس به‌روزرسانی شد.',
            'time_slot' => new TimeSlotResource($timeSlot),
        ]);
    }

    public function destroy(TimeSlot $timeSlot): JsonResponse
    {
        $timeSlot->delete();

        return response()->json(['message' => 'سانس حذف شد.']);
    }

    public function destroyMany(BulkIdsRequest $request): JsonResponse
    {
        $deleted = TimeSlot::query()->whereIn('id', $request->input('ids', []))->delete();

        return response()->json([
            'message' => $deleted > 0 ? "{$deleted} سانس حذف شد." : 'هیچ سانسی حذف نشد.',
            'deleted' => $deleted,
        ]);
    }
}
