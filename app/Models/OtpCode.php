<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OtpCode extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'mobile',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public static function issue(string $mobile): string
    {
        $resend = (int) config('registration.otp_resend_seconds');
        $ttl = (int) config('registration.otp_ttl_seconds');

        $pending = static::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($pending && $pending->created_at->greaterThan(now()->subSeconds($resend))) {
            throw ValidationException::withMessages([
                'mobile' => ['کد قبلی هنوز معتبر است. کمی بعد دوباره درخواست کنید.'],
            ]);
        }

        static::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(10000, 99999);

        static::query()->create([
            'mobile' => $mobile,
            'code_hash' => self::digest($code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return $code;
    }

    public static function consume(string $mobile, string $code): bool
    {
        return DB::transaction(function () use ($mobile, $code) {
            $otp = static::query()
                ->where('mobile', $mobile)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= (int) config('registration.otp_max_attempts')) {
                return false;
            }

            if (! hash_equals($otp->code_hash, self::digest($code))) {
                $otp->increment('attempts');

                return false;
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }

    private static function digest(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
