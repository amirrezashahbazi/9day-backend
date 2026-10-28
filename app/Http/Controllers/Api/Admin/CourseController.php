<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkIdsRequest;
use App\Http\Requests\Admin\IndexCoursesRequest;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index(IndexCoursesRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $courses = Course::query()
            ->with(['type', 'field', 'level', 'banners'])
            ->when(filled($filters['created_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when(filled($filters['created_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->when(filled($filters['gender'] ?? null), fn ($query) => $query->where('gender', $filters['gender']))
            ->when(filled($filters['course_type_id'] ?? null), fn ($query) => $query->where('course_type_id', $filters['course_type_id']))
            ->when(filled($filters['course_field_id'] ?? null), fn ($query) => $query->where('course_field_id', $filters['course_field_id']))
            ->when(filled($filters['course_level_id'] ?? null), fn ($query) => $query->where('course_level_id', $filters['course_level_id']))
            ->when(filled($filters['title'] ?? null), fn ($query) => $query->where('title', 'ilike', '%'.$filters['title'].'%'))
            ->when(filled($filters['enrollments_op'] ?? null) && array_key_exists('enrollments_value', $filters), function ($query) use ($filters) {
                $value = (int) $filters['enrollments_value'];
                match ($filters['enrollments_op']) {
                    'lt' => $query->where('enrollments_count', '<', $value),
                    'gt' => $query->where('enrollments_count', '>', $value),
                    'eq' => $query->where('enrollments_count', '=', $value),
                    default => null,
                };
            })
            ->latest('id')
            ->paginate($this->pageSize($filters));

        return response()->json([
            'data' => CourseResource::collection($courses)->resolve(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['banners', 'primary_index']);
        $files = array_values(array_filter($request->file('banners', []) ?? [], fn ($file) => $file instanceof UploadedFile));
        $primaryIndex = (int) ($request->input('primary_index') ?? 0);

        $course = DB::transaction(function () use ($request, $data, $files, $primaryIndex) {
            $course = Course::query()->create([
                ...$data,
                'enrollments_count' => 0,
                'created_by' => $request->user()?->id,
            ]);
            $this->replaceBanners(
                $course,
                array_map(fn (UploadedFile $file) => ['type' => 'new', 'file' => $file], $files),
                $primaryIndex,
            );

            return $course;
        });

        return response()->json([
            'message' => 'دوره ایجاد شد.',
            'course' => new CourseResource($course->load(['type', 'field', 'level', 'banners'])),
        ], 201);
    }

    public function show(Course $course): JsonResponse
    {
        return response()->json([
            'course' => new CourseResource($course->load(['type', 'field', 'level', 'banners'])),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $data = $request->safe()->except(['banners', 'keep_banner_ids', 'banner_order', 'primary_index']);
        $files = array_values(array_filter($request->file('banners', []) ?? [], fn ($file) => $file instanceof UploadedFile));
        $order = $request->bannerOrder();
        $primaryIndex = (int) ($request->input('primary_index') ?? 0);

        if ($order === []) {
            $keepIds = array_map('intval', $request->validated('keep_banner_ids') ?? []);
            $order = [
                ...array_map(fn (int $id) => ['type' => 'keep', 'id' => $id], $keepIds),
                ...array_map(fn (int $index) => ['type' => 'new', 'index' => $index], array_keys($files)),
            ];
        }

        DB::transaction(function () use ($course, $data, $order, $files, $primaryIndex) {
            $course->update($data);

            $plan = [];
            foreach ($order as $row) {
                if (($row['type'] ?? '') === 'keep' && isset($row['id'])) {
                    $plan[] = ['type' => 'keep', 'id' => (int) $row['id']];
                }
                if (($row['type'] ?? '') === 'new' && isset($row['index'], $files[$row['index']])) {
                    $plan[] = ['type' => 'new', 'file' => $files[$row['index']]];
                }
            }

            $this->replaceBanners($course, $plan, $primaryIndex);
        });

        return response()->json([
            'message' => 'دوره به‌روزرسانی شد.',
            'course' => new CourseResource($course->fresh()->load(['type', 'field', 'level', 'banners'])),
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->deleteCourse($course);

        return response()->json(['message' => 'دوره حذف شد.']);
    }

    public function destroyMany(BulkIdsRequest $request): JsonResponse
    {
        $courses = Course::query()->whereIn('id', $request->input('ids', []))->get();
        $deleted = 0;
        foreach ($courses as $course) {
            $this->deleteCourse($course);
            $deleted++;
        }

        return response()->json([
            'message' => $deleted > 0 ? "{$deleted} دوره حذف شد." : 'هیچ دوره‌ای حذف نشد.',
            'deleted' => $deleted,
        ]);
    }

    private function deleteCourse(Course $course): void
    {
        DB::transaction(function () use ($course) {
            $course->loadMissing('banners');
            foreach ($course->banners as $banner) {
                Storage::disk('public')->delete($banner->path);
            }
            $course->banners()->delete();
            $course->delete();
        });
    }

    /**
     * @param  list<array{type:string,id?:int,file?:UploadedFile}>  $plan
     */
    private function replaceBanners(Course $course, array $plan, int $primaryIndex): void
    {
        $keepIds = [];
        foreach ($plan as $row) {
            if (($row['type'] ?? '') === 'keep' && isset($row['id'])) {
                $keepIds[] = (int) $row['id'];
            }
        }

        $obsolete = $course->banners()->whereNotIn('id', $keepIds)->get();
        foreach ($obsolete as $banner) {
            Storage::disk('public')->delete($banner->path);
            $banner->delete();
        }

        if ($plan === []) {
            return;
        }

        $primaryIndex = max(0, min($primaryIndex, count($plan) - 1));

        foreach (array_values($plan) as $order => $row) {
            $isPrimary = $order === $primaryIndex;
            if (($row['type'] ?? '') === 'keep' && isset($row['id'])) {
                CourseBanner::query()->where('course_id', $course->id)->where('id', $row['id'])->update([
                    'sort_order' => $order,
                    'is_primary' => $isPrimary,
                ]);
                continue;
            }
            if (($row['type'] ?? '') === 'new' && ($row['file'] ?? null) instanceof UploadedFile) {
                $path = $row['file']->store("courses/{$course->id}", 'public');
                CourseBanner::query()->create([
                    'course_id' => $course->id,
                    'path' => $path,
                    'sort_order' => $order,
                    'is_primary' => $isPrimary,
                ]);
            }
        }
    }

    private function pageSize(array $filters): int
    {
        return min(500, max(1, (int) ($filters['per_page'] ?? 25)));
    }
}
