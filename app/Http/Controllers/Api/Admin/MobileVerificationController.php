<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MobileVerificationController extends Controller
{
    public static function cacheKey(string $mobile): string
    {
        return 'admin.mobile.verified.'.$mobile;
    }

    public function send(OtpRequest $request): JsonResponse
    {
        $mobile = $request->string('mobile')->toString();

        if (User::query()->where('mobile', $mobile)->exists()) {
            return response()->json([
                'message' => 'این موبایل قبلاً ثبت شده است.',
                'errors' => ['mobile' => ['این موبایل قبلاً ثبت شده است.']],
            ], 422);
        }

        $code = OtpCode::issue($mobile);

        Log::channel('otp')->info('Admin OTP issued for new user', [
            'mobile' => $mobile,
            'actor_id' => $request->user()?->id,
            'code' => $code,
        ]);

        Log::channel('stderr')->info('Admin OTP for '.$mobile.' is '.$code);

        return response()->json([
            'message' => 'کد تایید ارسال شد.',
            'expires_in' => (int) config('registration.otp_ttl_seconds'),
            'resend_in' => (int) config('registration.otp_resend_seconds'),
            'debug_code' => $code,
        ]);
    }

    public function confirm(VerifyOtpRequest $request): JsonResponse
    {
        $mobile = $request->string('mobile')->toString();
        $code = $request->string('code')->toString();

        if (User::query()->where('mobile', $mobile)->exists()) {
            return response()->json([
                'message' => 'این موبایل قبلاً ثبت شده است.',
                'errors' => ['mobile' => ['این موبایل قبلاً ثبت شده است.']],
            ], 422);
        }

        if (! OtpCode::consume($mobile, $code)) {
            return response()->json([
                'message' => 'کد تایید نادرست یا منقضی شده است.',
                'errors' => ['code' => ['کد تایید نادرست یا منقضی شده است.']],
            ], 422);
        }

        Cache::put(self::cacheKey($mobile), true, now()->addMinutes(30));

        Log::channel('admin')->info('Admin verified mobile for new user', [
            'mobile' => $mobile,
            'actor_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'شماره موبایل تایید شد.',
            'mobile' => $mobile,
            'verified' => true,
        ]);
    }
}
