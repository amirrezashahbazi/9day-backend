<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseField;
use App\Models\CourseLevel;
use App\Models\CourseSchedule;
use App\Models\CourseType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedPoolTimetable extends Command
{
    protected $signature = 'courses:seed-timetable';

    protected $description = 'Create the pool timetable courses from the weekly schedule sheet';

    public function handle(): int
    {
        $actorId = User::query()->orderBy('id')->value('id');
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($actorId, &$created, &$updated) {
            foreach ($this->courses() as $row) {
                $typeId = $this->taxonomyId(CourseType::class, $row['type']);
                $fieldId = $this->taxonomyId(CourseField::class, $row['field']);
                $levelId = $this->taxonomyId(CourseLevel::class, $row['level']);
                $course = Course::query()->where('title', $row['title'])->first();

                $attributes = [
                    'course_type_id' => $typeId,
                    'course_field_id' => $fieldId,
                    'course_level_id' => $levelId,
                    'gender' => $row['gender'],
                    'min_age' => $row['min_age'],
                    'max_age' => $row['max_age'],
                ];

                if ($course === null) {
                    $course = Course::query()->create([
                        ...$attributes,
                        'title' => $row['title'],
                        'fee' => 0,
                        'capacity' => $row['capacity'],
                        'sessions_count' => 12,
                        'enrollments_count' => 0,
                        'created_by' => $actorId,
                    ]);
                    $created++;
                } else {
                    $course->update($attributes);
                    $updated++;
                }

                $course->schedules()->delete();
                $now = now();
                $slots = [];
                foreach ($row['blocks'] as $block) {
                    foreach ($block['days'] as $day) {
                        $slots[] = [
                            'course_id' => $course->id,
                            'weekday' => $day,
                            'starts_at' => $block['start'],
                            'ends_at' => $block['end'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                CourseSchedule::query()->insert($slots);
            }
        });

        $this->info("Timetable ready. Created {$created}, updated {$updated}.");

        return self::SUCCESS;
    }

    /** @param  class-string<CourseType|CourseField|CourseLevel>  $model */
    private function taxonomyId(string $model, string $name): int
    {
        return (int) $model::query()->firstOrCreate(['name' => $name])->id;
    }

    /**
     * Weekdays are sat..fri. Even public days are Saturday, Monday and Wednesday.
     * The sheet header 19:30–22 is reversed as 22-19:30; adult swimming is written as 19:30–21.
     * Private and semi-private run in every open block, as the sheet note says.
     *
     * @return list<array{title: string, type: string, field: string, level: string, gender: ?string, min_age: ?int, max_age: ?int, capacity: int, blocks: list<array{days: list<string>, start: string, end: string}>}>
     */
    private function courses(): array
    {
        $even = ['sat', 'mon', 'wed'];
        $odd = ['sun', 'tue'];
        $midweek = ['sun', 'tue', 'thu'];
        $open = [
            ['days' => $even, 'ranges' => [['06:00', '08:00'], ['08:00', '10:00'], ['10:00', '12:00'], ['12:00', '14:00'], ['15:00', '16:30'], ['16:30', '18:00'], ['18:00', '19:30'], ['19:30', '22:00']]],
            ['days' => $odd, 'ranges' => [['06:00', '08:00'], ['08:00', '10:00'], ['10:00', '12:00'], ['12:00', '14:00'], ['14:00', '16:00'], ['16:00', '18:00'], ['18:00', '20:00'], ['20:00', '21:00']]],
            ['days' => ['thu'], 'ranges' => [['06:00', '08:00'], ['08:00', '10:00'], ['10:00', '12:00'], ['12:00', '14:00'], ['14:00', '16:00']]],
        ];
        $allHours = [];
        foreach ($open as $group) {
            foreach ($group['ranges'] as [$start, $end]) {
                $allHours[] = ['days' => $group['days'], 'start' => $start, 'end' => $end];
            }
        }

        return [
            $this->row('آبدرمانی', 'آبدرمانی', 'آبدرمانی', 'همه سنین', null, null, null, 20, [
                ['days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'], 'start' => '06:00', 'end' => '08:00'],
            ]),
            $this->row('آکادمی شنا', 'آکادمی', 'شنا', 'همه سنین', null, null, null, 20, [
                ['days' => $even, 'start' => '15:00', 'end' => '16:30'],
                ['days' => $odd, 'start' => '16:00', 'end' => '18:00'],
                ['days' => ['thu'], 'start' => '08:00', 'end' => '10:00'],
            ]),
            $this->row('آکادمی شیرجه', 'آکادمی', 'شیرجه', 'همه سنین', null, null, null, 20, [
                ['days' => $even, 'start' => '15:00', 'end' => '16:30'],
                ['days' => $odd, 'start' => '16:00', 'end' => '18:00'],
            ]),
            $this->row('آکادمی واترپلو (زیر ۱۴ سال)', 'آکادمی', 'واترپلو', 'زیر ۱۴ سال', null, null, 13, 20, [
                ['days' => $even, 'start' => '16:30', 'end' => '18:00'],
                ['days' => $odd, 'start' => '16:00', 'end' => '18:00'],
            ]),
            $this->row('آکادمی شنا (زیر ۱۵ سال)', 'آکادمی', 'شنا', 'زیر ۱۵ سال', null, null, 14, 20, [
                ['days' => $even, 'start' => '16:30', 'end' => '18:00'],
                ['days' => $even, 'start' => '18:00', 'end' => '19:30'],
                ['days' => $odd, 'start' => '20:00', 'end' => '21:00'],
            ]),
            $this->row('آکادمی واترپلو (زیر ۱۲ سال)', 'آکادمی', 'واترپلو', 'زیر ۱۲ سال', null, null, 11, 20, [
                ['days' => $even, 'start' => '18:00', 'end' => '19:30'],
                ['days' => $odd, 'start' => '18:00', 'end' => '20:00'],
            ]),
            $this->row('آکادمی واترپلو (زیر ۱۸ سال)', 'آکادمی', 'واترپلو', 'زیر ۱۸ سال', null, null, 17, 20, [
                ['days' => $even, 'start' => '18:00', 'end' => '19:30'],
                ['days' => $odd, 'start' => '18:00', 'end' => '20:00'],
            ]),
            $this->row('واترپلو بزرگسال', 'آکادمی', 'واترپلو', 'بزرگسال', null, 18, null, 20, [
                ['days' => $even, 'start' => '19:30', 'end' => '22:00'],
            ]),
            $this->row('شیرجه بزرگسال', 'آکادمی', 'شیرجه', 'بزرگسال', null, 18, null, 20, [
                ['days' => $even, 'start' => '19:30', 'end' => '22:00'],
            ]),
            $this->row('آکادمی شنا (بزرگسال)', 'آکادمی', 'شنا', 'بزرگسال', null, 18, null, 20, [
                ['days' => $even, 'start' => '19:30', 'end' => '21:00'],
            ]),
            $this->row('بانوان', 'آزاد', 'شنا', 'همه سنین', 'female', null, null, 20, [
                ['days' => $midweek, 'start' => '12:00', 'end' => '14:00'],
            ]),
            $this->row('مدرسه شنا بانوان', 'مدرسه', 'شنا', 'همه سنین', 'female', null, null, 20, [
                ['days' => $midweek, 'start' => '14:00', 'end' => '16:00'],
            ]),
            $this->row('آکادمی واترپلو', 'آکادمی', 'واترپلو', 'همه سنین', null, null, null, 20, [
                ['days' => ['thu'], 'start' => '10:00', 'end' => '12:00'],
            ]),
            $this->row('خصوصی', 'خصوصی', 'عمومی', 'همه سنین', null, null, null, 1, $allHours),
            $this->row('نیمه‌خصوصی', 'نیمه‌خصوصی', 'عمومی', 'همه سنین', null, null, null, 2, $allHours),
        ];
    }

    /**
     * @param  list<array{days: list<string>, start: string, end: string}>  $blocks
     * @return array{title: string, type: string, field: string, level: string, gender: ?string, min_age: ?int, max_age: ?int, capacity: int, blocks: list<array{days: list<string>, start: string, end: string}>}
     */
    private function row(string $title, string $type, string $field, string $level, ?string $gender, ?int $minAge, ?int $maxAge, int $capacity, array $blocks): array
    {
        return [
            'title' => $title,
            'type' => $type,
            'field' => $field,
            'level' => $level,
            'gender' => $gender,
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'capacity' => $capacity,
            'blocks' => $blocks,
        ];
    }
}
