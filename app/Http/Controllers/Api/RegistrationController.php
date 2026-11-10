<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\SaveRegistrationStepRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\RegistrationStepSaver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['province', 'city', 'district', 'kinships.relative']);

        if (! $user->isMember() || $user->isStaff()) {
            abort(403, 'این بخش مخصوص ثبت‌نام کاربران است.');
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function update(SaveRegistrationStepRequest $request, int $step, RegistrationStepSaver $saver): JsonResponse
    {
        $user = $saver->save($request->user(), $step, $request);

        return response()->json([
            'message' => $step === 7 ? 'ثبت‌نام شما نهایی شد.' : 'این مرحله ذخیره شد.',
            'user' => new UserResource($user),
        ]);
    }

    public function lookupKin(Request $request): JsonResponse
    {
        $mobile = trim((string) $request->query('mobile', ''));
        if (! preg_match('/^09[0-9]{9}$/', $mobile)) {
            return response()->json([
                'message' => 'موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
                'errors' => ['mobile' => ['موبایل باید ۱۱ رقم و با ۰۹ شروع شود.']],
            ], 422);
        }

        $user = User::query()->where('mobile', $mobile)->first();

        return response()->json([
            'user' => $user ? [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'mobile' => $user->mobile,
            ] : null,
        ]);
    }
}
