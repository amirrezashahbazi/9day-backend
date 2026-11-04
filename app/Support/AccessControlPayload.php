<?php

namespace App\Support;

use App\Models\User;

class AccessControlPayload
{
    /** @param  array<string, mixed>  $payload */
    public static function resolve(array $payload): array
    {
        $cardNumber = self::extractCardNumber($payload);
        $payloadName = self::extractUsername($payload);

        $user = $cardNumber !== null ? self::findUserByCard($cardNumber) : null;

        $userName = null;
        if ($user) {
            $userName = trim(collect([$user->first_name, $user->last_name])->filter()->implode(' '));
            if ($userName === '') {
                $userName = $user->mobile;
            }
        } elseif (is_string($payloadName) && trim($payloadName) !== '') {
            $userName = trim($payloadName);
        }

        return [
            'card_number' => $cardNumber,
            'user_name' => $userName,
            'user_id' => $user?->id,
        ];
    }

    public static function canonicalCard(string $card): string
    {
        $digits = preg_replace('/\D+/', '', $card) ?? '';
        $trimmed = ltrim($digits, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    public static function findUserByCard(string $card): ?User
    {
        $exact = User::query()->where('access_card_number', $card)->first();
        if ($exact) {
            return $exact;
        }

        $canonical = self::canonicalCard($card);
        if ($canonical === '0') {
            return null;
        }

        return User::query()
            ->whereRaw("ltrim(access_card_number, '0') = ?", [$canonical])
            ->orderByDesc('id')
            ->first();
    }

    /** @param  array<string, mixed>  $payload */
    public static function extractCardNumber(array $payload): ?string
    {
        $keys = [
            'card_number', 'cardNumber', 'cardno', 'CardNo', 'cardNo',
            'carddata', 'CardData', 'cardData', 'card',
            'enrollid', 'EnrollID', 'enrollId', 'enroll_id',
            'pin', 'Pin', 'PIN',
        ];

        foreach (self::walk($payload) as $key => $value) {
            if (! in_array((string) $key, $keys, true)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (is_string($digits) && strlen($digits) >= 4) {
                return $digits;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    public static function extractUsername(array $payload): ?string
    {
        $keys = ['username', 'userName', 'UserName', 'name', 'Name', 'fullname'];

        foreach (self::walk($payload) as $key => $value) {
            if (! in_array((string) $key, $keys, true)) {
                continue;
            }
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Generator<string|int, mixed>
     */
    private static function walk(array $payload): \Generator
    {
        foreach ($payload as $key => $value) {
            yield $key => $value;
            if (is_array($value)) {
                yield from self::walk($value);
            }
        }
    }
}
