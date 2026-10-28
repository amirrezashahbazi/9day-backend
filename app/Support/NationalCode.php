<?php

namespace App\Support;

class NationalCode
{
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{10}$/', $value)) {
            return false;
        }

        $check = (int) $value[9];
        $sum = 0;

        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $value[$index]) * (10 - $index);
        }

        $sum %= 11;

        return $sum < 2 ? $check === $sum : $check + $sum === 11;
    }

    public static function sample(): string
    {
        $base = '049937089';

        return $base.self::checkDigit($base);
    }

    private static function checkDigit(string $base): int
    {
        $sum = 0;

        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $base[$index]) * (10 - $index);
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? $remainder : 11 - $remainder;
    }
}
