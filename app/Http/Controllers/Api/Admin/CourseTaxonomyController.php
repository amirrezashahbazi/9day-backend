<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkIdsRequest;
use App\Http\Requests\Admin\StoreCourseTaxonomyRequest;
use App\Http\Requests\Admin\UpdateCourseTaxonomyRequest;
use App\Http\Resources\CourseTaxonomyResource;
use App\Models\Course;
use App\Models\CourseField;
use App\Models\CourseLevel;
use App\Models\CourseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseTaxonomyController extends Controller
{
    public function index(Request $request, string $taxonomyTable): JsonResponse
    {
        $model = $this->model($taxonomyTable);
        $foreign = $this->foreignKey($taxonomyTable);

        $items = $model::query()
            ->withCount(['courses' => fn ($query) => $query->withTrashed()])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CourseTaxonomyResource::collection($items)->resolve(),
            'meta' => ['foreign_key' => $foreign],
        ]);
    }

    public function store(StoreCourseTaxonomyRequest $request, string $taxonomyTable): JsonResponse
    {
        $model = $this->model($taxonomyTable);
        $item = $model::query()->create(['name' => $request->validated('name')]);

        return response()->json([
            'message' => 'مورد جدید ثبت شد.',
            'item' => new CourseTaxonomyResource($item->loadCount(['courses' => fn ($query) => $query->withTrashed()])),
        ], 201);
    }

    public function update(UpdateCourseTaxonomyRequest $request, string $taxonomyTable, int $id): JsonResponse
    {
        $model = $this->model($taxonomyTable);
        /** @var Model $item */
        $item = $model::query()->findOrFail($id);
        $item->update(['name' => $request->validated('name')]);

        return response()->json([
            'message' => 'مورد به‌روزرسانی شد.',
            'item' => new CourseTaxonomyResource($item->loadCount(['courses' => fn ($query) => $query->withTrashed()])),
        ]);
    }

    public function destroy(string $taxonomyTable, int $id): JsonResponse
    {
        $model = $this->model($taxonomyTable);
        /** @var Model $item */
        $item = $model::query()->findOrFail($id);
        $result = $this->deleteTaxonomyItem($taxonomyTable, $item);
        if ($result !== true) {
            return response()->json(['message' => $result], 422);
        }

        return response()->json(['message' => 'مورد حذف شد.']);
    }

    public function destroyMany(BulkIdsRequest $request, string $taxonomyTable): JsonResponse
    {
        $model = $this->model($taxonomyTable);
        $items = $model::query()->whereIn('id', $request->input('ids', []))->get();
        $deleted = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $result = $this->deleteTaxonomyItem($taxonomyTable, $item);
            if ($result === true) {
                $deleted++;
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'message' => $deleted > 0
                ? "{$deleted} مورد حذف شد.".($skipped ? " {$skipped} مورد قابل حذف نبود." : '')
                : 'هیچ موردی حذف نشد.',
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    private function deleteTaxonomyItem(string $taxonomyTable, Model $item): true|string
    {
        $foreign = $this->foreignKey($taxonomyTable);
        $used = Course::withTrashed()->where($foreign, $item->getKey())->exists();
        if ($used) {
            return 'این مورد روی حداقل یک دوره استفاده شده و قابل حذف نیست.';
        }
        $item->delete();

        return true;
    }

    /** @return class-string<Model> */
    private function model(string $table): string
    {
        return match ($table) {
            'course_types' => CourseType::class,
            'course_fields' => CourseField::class,
            'course_levels' => CourseLevel::class,
            default => abort(404),
        };
    }

    private function foreignKey(string $table): string
    {
        return match ($table) {
            'course_types' => 'course_type_id',
            'course_fields' => 'course_field_id',
            'course_levels' => 'course_level_id',
            default => abort(404),
        };
    }
}
