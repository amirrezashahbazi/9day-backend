<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    public function send(OtpRequest $request): JsonResponse
    {
        $mobile = $request->string('mobile')->toString();
        $purpose = $request->input('purpose', 'login');
        $exists = User::query()->where('mobile', $mobile)->exists();

        if ($purpose === 'register' && $exists) {
            return response()->json([
                'message' => 'این موبایل قبلاً ثبت شده است.',
                'errors' => [
                    'mobile' => ['این موبایل قبلاً ثبت شده است.'],
                ],
            ], 422);
        }

        if ($purpose === 'login' && ! $exists) {
            return response()->json([
                'message' => 'این موبایل ثبت نشده است.',
                'errors' => [
                    'mobile' => ['این موبایل ثبت نشده است. برای ثبت‌نام از بخش ثبت‌نام استفاده کنید.'],
                ],
            ], 422);
        }

        $code = OtpCode::issue($mobile);

        Log::channel('otp')->info('OTP issued', [
            'mobile' => $mobile,
            'purpose' => $purpose,
            'code' => $code,
        ]);

        Log::channel('stderr')->info('OTP for '.$mobile.' is '.$code);

        return response()->json([
            'message' => 'کد تایید صادر شد.',
            'expires_in' => (int) config('registration.otp_ttl_seconds'),
            'debug_code' => $code,
        ]);
    }

    public function confirm(VerifyOtpRequest $request): JsonResponse
    {
        $mobile = $request->string('mobile')->toString();
        $code = $request->string('code')->toString();
        $existing = User::query()->where('mobile', $mobile)->first();

        if (! OtpCode::consume($mobile, $code)) {
            Log::channel('auth')->warning('OTP verification failed', ['mobile' => $mobile]);

            return response()->json([
                'message' => 'کد تایید نادرست یا منقضی شده است.',
                'errors' => [
                    'code' => ['کد تایید نادرست یا منقضی شده است.'],
                ],
            ], 422);
        }

        $user = $existing ?? User::query()->create([
            'mobile' => $mobile,
            'status' => UserStatus::Draft,
            'registration_step' => 0,
            'password' => null,
        ]);

        if ($user->roles()->doesntExist()) {
            $user->syncRoles([UserRole::User]);
        }

        if ($user->phone_verified_at === null) {
            $user->phone_verified_at = now();
            $user->save();
        }

        if ($user->status === UserStatus::Blocked) {
            Log::channel('auth')->warning('Blocked user OTP login', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'دسترسی این حساب مسدود شده است.',
            ], 403);
        }

        $token = $user->createToken('panel')->plainTextToken;

        Log::channel('auth')->info('User logged in with OTP', [
            'user_id' => $user->id,
            'mobile' => $mobile,
        ]);

        return response()->json([
            'message' => $existing ? 'ورود انجام شد.' : 'شماره موبایل تایید شد.',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
