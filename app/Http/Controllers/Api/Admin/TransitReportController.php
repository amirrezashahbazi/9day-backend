<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GateTransit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransitReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:allowed,denied'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = GateTransit::query()->with(['user' => fn ($user) => $user->withTrashed()]);

        if (filled($filters['created_from'] ?? null)) {
            $query->where('created_at', '>=', Carbon::parse($filters['created_from'], 'Asia/Tehran')->startOfDay()->utc());
        }
        if (filled($filters['created_to'] ?? null)) {
            $query->where('created_at', '<=', Carbon::parse($filters['created_to'], 'Asia/Tehran')->endOfDay()->utc());
        }
        if (($filters['status'] ?? null) === 'allowed') {
            $query->where('allowed', true);
        }
        if (($filters['status'] ?? null) === 'denied') {
            $query->where('allowed', false);
        }
        if (filled($filters['q'] ?? null)) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('user_name', 'ilike', $term)
                    ->orWhere('card_number', 'like', $term)
                    ->orWhere('course_title', 'ilike', $term)
                    ->orWhereHas('user', function ($user) use ($term) {
                        $user->withTrashed()->where(function ($match) use ($term) {
                            $match->where('first_name', 'ilike', $term)
                                ->orWhere('last_name', 'ilike', $term)
                                ->orWhere('mobile', 'like', $term);
                        });
                    });
            });
        }

        $summary = (clone $query)
            ->selectRaw('count(*) as total, count(*) filter (where allowed) as allowed, count(*) filter (where not allowed) as denied')
            ->first();

        $transits = $query->latest('id')->paginate(min(500, max(1, (int) ($filters['per_page'] ?? 20))));

        return response()->json([
            'data' => $transits->getCollection()->map(fn (GateTransit $transit) => $this->payload($transit))->values(),
            'meta' => [
                'current_page' => $transits->currentPage(),
                'last_page' => $transits->lastPage(),
                'per_page' => $transits->perPage(),
                'total' => (int) ($summary->total ?? 0),
                'allowed' => (int) ($summary->allowed ?? 0),
                'denied' => (int) ($summary->denied ?? 0),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(GateTransit $transit): array
    {
        $user = $transit->user;

        return [
            'id' => $transit->id,
            'allowed' => $transit->allowed,
            'reason' => $transit->reason?->value,
            'card_number' => $transit->card_number,
            'course_title' => $transit->course_title,
            'sessions_remaining' => $transit->sessions_remaining_after,
            'user_name' => $transit->user_name,
            'created_at' => $transit->created_at?->toIso8601String(),
            'user' => $user ? [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'mobile' => $user->mobile,
            ] : null,
        ];
    }
}
