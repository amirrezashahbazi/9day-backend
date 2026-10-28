<?php

namespace App\Services;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DivarPlaces
{
    public function provinces(): array
    {
        $cached = Cache::get('divar.places.tree');
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $this->syncPlaces();
        $tree = Province::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Province $province) => [
                'id' => $province->id,
                'name' => $province->name,
                'cities' => $province->cities->map(fn (City $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                ])->values(),
            ])
            ->values()
            ->all();

        if ($tree !== []) {
            Cache::put('divar.places.tree', $tree, now()->addDays(7));
        }

        return $tree;
    }

    public function districts(City $city): array
    {
        $flag = $this->districtFlag($city);
        if ($flag === false) {
            return [];
        }

        if ($flag === true) {
            return $this->storedDistricts($city);
        }

        $this->syncDistricts($city);
        $city->refresh();

        return $this->districtFlag($city) === true ? $this->storedDistricts($city) : [];
    }

    public function requiresDistrict(City $city): bool
    {
        if ($this->districtFlag($city) === null) {
            $this->syncDistricts($city);
            $city->refresh();
        }

        return $this->districtFlag($city) === true;
    }

    public function syncPlaces(): void
    {
        $response = Http::timeout(15)
            ->withHeaders($this->headers())
            ->withBody('{}', 'application/json')
            ->post('https://api.divar.ir/v8/postlist/w/places');

        if (! $response->successful()) {
            return;
        }

        foreach ($response->json('root.children') ?? [] as $province) {
            if (($province['type'] ?? '') !== 'PROVINCE' || ! isset($province['id'], $province['name'])) {
                continue;
            }

            $row = Province::withTrashed()->firstOrNew(['divar_id' => (string) $province['id']]);
            $row->name = $province['name'];
            $row->deleted_at = null;
            $row->save();

            foreach ($province['children'] ?? [] as $city) {
                if (($city['type'] ?? '') !== 'CITY' || ! isset($city['id'], $city['name'])) {
                    continue;
                }

                $cityRow = City::withTrashed()->firstOrNew(['divar_id' => (string) $city['id']]);
                $cityRow->province_id = $row->id;
                $cityRow->name = $city['name'];
                $cityRow->deleted_at = null;
                $cityRow->save();
            }
        }
    }

    public function syncDistricts(City $city): void
    {
        $cacheKey = 'divar.districts.'.$city->divar_id;
        $items = Cache::get($cacheKey);

        if (! is_array($items)) {
            $response = Http::timeout(12)
                ->withHeaders($this->headers())
                ->post('https://api.divar.ir/v8/w/lazy-multi-select-hierarchy-options', [
                    'payload' => [
                        '@type' => 'type.googleapis.com/post_list.LazyFilterPayload',
                        'filter_name' => 'districts',
                        'version' => '82',
                        'place_ids' => [$city->divar_id],
                        'category' => 'ROOT',
                    ],
                ]);

            if (! $response->successful()) {
                return;
            }

            $items = [];
            foreach ($response->json('options.children') ?? [] as $child) {
                $id = $child['data']['key'] ?? null;
                $title = $child['data']['title'] ?? null;
                if (! is_string($id) || $id === '' || ! is_string($title) || $title === '') {
                    continue;
                }
                $items[] = ['divar_id' => $id, 'name' => $title];
            }

            Cache::put($cacheKey, $items, now()->addDays(7));
        }

        if ($items === []) {
            $city->forceFill(['has_districts' => false])->save();

            return;
        }

        foreach ($items as $item) {
            $district = District::withTrashed()->firstOrNew([
                'city_id' => $city->id,
                'divar_id' => $item['divar_id'],
            ]);
            $district->name = $item['name'];
            $district->deleted_at = null;
            $district->save();
        }

        $city->forceFill(['has_districts' => true])->save();
    }

    private function districtFlag(City $city): ?bool
    {
        $value = $city->getAttributes()['has_districts'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($value, [true, 1, '1', 't', 'true'], true)) {
            return true;
        }

        if (in_array($value, [false, 0, '0', 'f', 'false'], true)) {
            return false;
        }

        return null;
    }

    private function storedDistricts(City $city): array
    {
        return $city->districts()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district) => ['id' => $district->id, 'name' => $district->name])
            ->all();
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Origin' => 'https://divar.ir',
            'Referer' => 'https://divar.ir/',
            'User-Agent' => 'Mozilla/5.0',
            'X-Web-Serving-Mode' => 'desktop',
        ];
    }
}
