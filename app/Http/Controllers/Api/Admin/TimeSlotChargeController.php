<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Gender;
use App\Enums\SlotPeriod;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTimeSlotChargesRequest;
use App\Http\Requests\Admin\StoreTimeSlotChargeRequest;
use App\Http\Resources\TimeSlotChargeResource;
use App\Models\TimeSlot;
use App\Models\TimeSlotCharge;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TimeSlotChargeController extends Controller
{
    public function index(IndexTimeSlotChargesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = TimeSlotCharge::query()
            ->with('user')
            ->when(filled($filters['created_from'] ?? null), fn ($builder) => $builder->whereDate('created_at', '>=', $filters['created_from']))
            ->when(filled($filters['created_to'] ?? null), fn ($builder) => $builder->whereDate('created_at', '<=', $filters['created_to']))
            ->when(filled($filters['period'] ?? null), fn ($builder) => $builder->where('period', $filters['period']))
            ->when(filled($filters['q'] ?? null), function ($builder) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $builder->whereHas('user', function ($user) use ($term) {
                    $user->withTrashed()->where(function ($match) use ($term) {
                        $match->where('first_name', 'ilike', $term)
                            ->orWhere('last_name', 'ilike', $term)
                            ->orWhere('mobile', 'ilike', $term)
                            ->orWhere('file_number', 'ilike', $term);
                    });
                });
            });

        $totalAmount = (int) (clone $query)->sum('amount');
        $totalSessions = (int) (clone $query)->sum('sessions_count');
        $slots = (clone $query)
            ->selectRaw('slot_label, period, count(*) as charges, coalesce(sum(amount), 0) as amount, coalesce(sum(sessions_count), 0) as sessions')
            ->groupBy('slot_label', 'period')
            ->orderBy('slot_label')
            ->get();
        $charges = $query->latest('id')->paginate(min(500, max(1, (int) ($filters['per_page'] ?? 20))));

        return response()->json([
            'data' => TimeSlotChargeResource::collection($charges)->resolve(),
            'meta' => [
                'current_page' => $charges->currentPage(),
                'last_page' => $charges->lastPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
                'total_amount' => $totalAmount,
                'total_sessions' => $totalSessions,
                'slots' => $this->slotSummaries($slots),
            ],
        ]);
    }

    public function store(StoreTimeSlotChargeRequest $request, User $user): JsonResponse
    {
        $slot = TimeSlot::query()->findOrFail($request->integer('time_slot_id'));

        $charge = TimeSlotCharge::query()->create([
            'user_id' => $user->id,
            'time_slot_id' => $slot->id,
            'sessions_count' => $request->integer('sessions_count'),
            'amount' => $request->integer('amount'),
            'slot_label' => $this->label($slot),
            'gender' => $slot->gender?->value,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'period' => $slot->period?->value,
            'created_by' => $request->user()?->id,
        ]);

        $charge->load('user');

        return response()->json([
            'message' => 'سانس برای کاربر شارژ شد.',
            'charge' => new TimeSlotChargeResource($charge),
        ], 201);
    }

    /** @param Collection<int, TimeSlotCharge> $slots
     * @return list<array{slot_label: string, period: ?string, charges: int, amount: int, sessions: int}> */
    private function slotSummaries(Collection $slots): array
    {
        return $slots->map(function (TimeSlotCharge $row) {
            $period = $row->period instanceof SlotPeriod ? $row->period->value : $row->period;

            return [
                'slot_label' => (string) $row->slot_label,
                'period' => is_string($period) ? $period : null,
                'charges' => (int) $row->charges,
                'amount' => (int) $row->amount,
                'sessions' => (int) $row->sessions,
            ];
        })->values()->all();
    }

    private function label(TimeSlot $slot): string
    {
        $gender = $slot->gender === Gender::Female ? 'بانوان' : 'آقایان';
        $period = $slot->period === SlotPeriod::Morning ? 'صبح' : 'ظهر';

        return trim($gender.' '.$this->clock($slot->starts_at).'-'.$this->clock($slot->ends_at).' '.$period);
    }

    private function clock(mixed $value): string
    {
        $clock = is_string($value) ? substr($value, 0, 5) : '';
        if (! preg_match('/^(\d{2}):(\d{2})$/', $clock, $parts)) {
            return $clock;
        }

        return $parts[2] === '00' ? (string) ((int) $parts[1]) : $clock;
    }
}
