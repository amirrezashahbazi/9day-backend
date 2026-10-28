<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StaffAuthController extends Controller
{
    public function login(StaffLoginRequest $request): JsonResponse
    {
        $mobile = $request->string('mobile')->toString();
        $user = User::query()->where('mobile', $mobile)->first();

        if (! $user || ! $user->password || ! Hash::check($request->string('password')->toString(), $user->password)) {
            Log::channel('auth')->warning('Password login failed', ['mobile' => $mobile]);

            return response()->json([
                'message' => 'موبایل یا رمز عبور نادرست است.',
            ], 422);
        }

        if ($user->status === UserStatus::Blocked) {
            Log::channel('auth')->warning('Blocked password login', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'دسترسی این حساب مسدود شده است.',
            ], 403);
        }

        $token = $user->createToken('panel')->plainTextToken;

        Log::channel('auth')->info('Password login', [
            'user_id' => $user->id,
            'role' => $user->primaryRole(),
        ]);

        return response()->json([
            'message' => 'ورود انجام شد.',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
