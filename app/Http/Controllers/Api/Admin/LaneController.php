<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkIdsRequest;
use App\Http\Requests\Admin\LaneRequest;
use App\Models\Lane;
use App\Models\LaneType;
use Illuminate\Http\JsonResponse;

class LaneController extends Controller
{
    public function types(): JsonResponse
    {
        $types = LaneType::query()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'data' => $types->map(fn (LaneType $type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])->values(),
        ]);
    }

    public function index(): JsonResponse
    {
        $lanes = Lane::query()->with('type')->withCount('rentals')->orderByDesc('id')->get();

        return response()->json([
            'data' => $lanes->map(fn (Lane $lane) => $this->payload($lane))->values(),
        ]);
    }

    public function store(LaneRequest $request): JsonResponse
    {
        $lane = Lane::query()->create([
            'lane_type_id' => $request->typeId(),
            'title' => $request->validated('title'),
            'fee' => $request->integer('fee'),
            'min_people' => $request->integer('min_people'),
            'max_people' => $request->integer('max_people'),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'لاین ثبت شد.',
            'lane' => $this->payload($lane->load('type')->loadCount('rentals')),
        ], 201);
    }

    public function update(LaneRequest $request, Lane $lane): JsonResponse
    {
        $lane->update([
            'lane_type_id' => $request->typeId(),
            'title' => $request->validated('title'),
            'fee' => $request->integer('fee'),
            'min_people' => $request->integer('min_people'),
            'max_people' => $request->integer('max_people'),
        ]);

        return response()->json([
            'message' => 'لاین به‌روزرسانی شد.',
            'lane' => $this->payload($lane->load('type')->loadCount('rentals')),
        ]);
    }

    public function destroy(Lane $lane): JsonResponse
    {
        if ($lane->rentals()->exists()) {
            return response()->json(['message' => 'این لاین به اجاره‌کننده تخصیص داده شده و حذف نمی‌شود.'], 422);
        }

        $lane->delete();

        return response()->json(['message' => 'لاین حذف شد.']);
    }

    public function destroyMany(BulkIdsRequest $request): JsonResponse
    {
        $lanes = Lane::query()->withCount('rentals')->whereIn('id', $request->input('ids', []))->get();
        $deleted = 0;
        foreach ($lanes as $lane) {
            if ($lane->rentals_count > 0) {
                continue;
            }
            $lane->delete();
            $deleted++;
        }

        return response()->json([
            'message' => $deleted > 0 ? "{$deleted} لاین حذف شد." : 'لاینی حذف نشد. لاین‌های تخصیص‌داده‌شده می‌مانند.',
            'deleted' => $deleted,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Lane $lane): array
    {
        return [
            'id' => $lane->id,
            'title' => $lane->title,
            'lane_type_id' => $lane->lane_type_id,
            'type' => $lane->type ? ['id' => $lane->type->id, 'name' => $lane->type->name] : null,
            'fee' => $lane->fee,
            'min_people' => $lane->min_people,
            'max_people' => $lane->max_people,
            'rentals_count' => (int) ($lane->rentals_count ?? $lane->rentals()->count()),
            'created_at' => $lane->created_at?->toIso8601String(),
        ];
    }
}
