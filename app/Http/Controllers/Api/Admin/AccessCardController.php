<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnrollAccessCardRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccessControlDeviceClient;
use Illuminate\Http\JsonResponse;

class AccessCardController extends Controller
{
    public function enroll(EnrollAccessCardRequest $request, User $user, AccessControlDeviceClient $device): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->hasRole(UserRole::SuperAdmin) && $user->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'به این حساب دسترسی ندارید.');
        }
        if ($actor->hasRole(UserRole::Admin) && ! $actor->hasRole(UserRole::SuperAdmin) && ! $user->isMember()) {
            abort(403, 'ادمین فقط می‌تواند کاربران عادی را مدیریت کند.');
        }

        $cardNumber = $request->string('card_number')->toString();
        $result = $device->setUserInfo($user, $cardNumber);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'device_status' => $result['status'],
                'device_body' => $result['body'],
            ], 502);
        }

        $user->forceFill([
            'access_card_number' => $cardNumber,
            'access_card_enrolled_at' => now(),
        ])->save();

        $user->loadMissing(['roles.permissions', 'permissions', 'province', 'city', 'district', 'introducer']);

        return response()->json([
            'message' => $result['message'],
            'user' => new UserResource($user),
            'device_status' => $result['status'],
            'device_body' => $result['body'],
        ]);
    }
}
