<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FederationMembershipClient
{
    public const TOKEN = '1efc2d96a6880159e46762f13f36f69dfc607f84d0a1dd0105c628e6cab96942';

    public const URL = 'https://register.irsf.ir/api/membership/status';

    /**
     * @return array<string, mixed>
     */
    public function inquire(string $nationalCode): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken(self::TOKEN)
                ->timeout(20)
                ->connectTimeout(8)
                ->post(self::URL, [
                    'national_card_id' => $nationalCode,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('پاسخ فدراسیون در زمان مقرر نرسید. دوباره استعلام بگیرید.', 0, $exception);
        }

        if ($response->failed()) {
            $message = $response->json('message');
            throw new RuntimeException(is_string($message) && $message !== '' ? $message : 'استعلام فدراسیون ناموفق بود.');
        }

        $body = $response->json();
        if (! is_array($body) || ! is_array($body['user'] ?? null)) {
            throw new RuntimeException('برای این کد ملی در فدراسیون رکوردی پیدا نشد.');
        }

        $mapped = $this->map($body);
        Cache::put($this->cacheKey($nationalCode), $mapped, now()->addHours(6));

        return $mapped;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cached(string $nationalCode): ?array
    {
        $value = Cache::get($this->cacheKey($nationalCode));

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function map(array $body): array
    {
        $user = $body['user'];
        $gender = match ((string) ($user['gender'] ?? '')) {
            '1' => 'male',
            '2' => 'female',
            default => null,
        };

        $cityCode = preg_replace('/\D+/', '', (string) ($user['city_code'] ?? '')) ?? '';
        $phone = preg_replace('/\D+/', '', (string) ($user['phone'] ?? '')) ?? '';
        $landline = $cityCode.$phone;
        if (! preg_match('/^0[1-8]\d{9}$/', $landline)) {
            $landline = null;
        }

        $issued = $user['city_issuing']['name'] ?? null;
        $accepted = collect($body['experiences'] ?? [])
            ->filter(fn ($row) => is_array($row) && (($row['status']['name'] ?? null) === 'accept'))
            ->values();

        return [
            'active' => (bool) ($body['active'] ?? false),
            'expires_at' => $body['expires_at'] ?? null,
            'form' => [
                'first_name' => $user['name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'father_name' => $user['father_name'] ?? '',
                'issued_from' => is_string($issued) ? $issued : '',
                'national_code' => $user['national_card_id'] ?? '',
                'gender' => $gender,
                'birth_date' => $user['birthday'] ?? '',
                'address' => $user['address'] ?? '',
                'landline' => $landline,
                'has_sport_history' => $accepted->isNotEmpty(),
            ],
            'profile' => [
                'name_en' => $user['name_en'] ?? null,
                'last_name_en' => $user['last_name_en'] ?? null,
                'mobile' => $user['mobile'] ?? null,
                'email' => $user['email'] ?? null,
                'province_living' => $user['province_living']['name'] ?? null,
                'province_birth' => $user['province_birth']['name'] ?? null,
                'city_birth' => $user['city_birth']['name'] ?? null,
                'personnel_image_url' => $user['personnel_image_url'] ?? null,
            ],
            'documents' => collect($body['documents'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => [
                    'name' => $row['name'] ?? 'مدرک',
                    'url' => $row['url'] ?? null,
                ])
                ->filter(fn (array $row) => is_string($row['url']) && $row['url'] !== '')
                ->values()
                ->all(),
            'experiences' => collect($body['experiences'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => [
                    'id' => $row['id'] ?? null,
                    'status' => $row['status']['name'] ?? null,
                    'start_date' => $row['start_date'] ?? null,
                    'end_date' => $row['end_date'] ?? null,
                    'province' => $row['province']['name'] ?? null,
                    'sports' => collect($row['sports'] ?? [])->pluck('name')->filter()->values()->all(),
                    'kinds' => collect($row['kinds'] ?? [])->pluck('name')->filter()->values()->all(),
                    'levels' => collect($row['levels'] ?? [])->pluck('name')->filter()->values()->all(),
                    'document_image_url' => $row['document_image_url'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function cacheKey(string $nationalCode): string
    {
        return 'federation-inquiry:'.$nationalCode;
    }
}
