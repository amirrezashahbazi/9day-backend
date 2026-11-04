<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getInt(string $key, int $default): int
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value === null ? $default : max(1, (int) $value);
    }

    public static function getString(string $key, string $default = ''): string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function putValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
