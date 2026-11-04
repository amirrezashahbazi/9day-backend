<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Events\AccessControlLogReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnrollAccessCardRequest;
use App\Http\Resources\UserResource;
use App\Models\AccessControlLog;
use App\Models\User;
use App\Services\AccessControlDeviceClient;
use Illuminate\Http\JsonResponse;

class AccessCardController extends Controller
{
    public function enroll(EnrollAccessCardRequest $request, User $user, AccessControlDeviceClient $device): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'فقط سوپر ادمین می‌تواند کارت کنترل تردد را مدیریت کند.');
        }
        if ($user->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'به این حساب دسترسی ندارید.');
        }

        $cardNumber = $request->string('card_number')->toString();
        $result = $device->setUserInfo($user, $cardNumber);

        $this->storeDeviceResponseLog($request, $user, $cardNumber, $result);

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

    /**
     * @param  array{ok: bool, message: string, status: int|null, body: mixed}  $result
     */
    private function storeDeviceResponseLog(EnrollAccessCardRequest $request, User $user, string $cardNumber, array $result): void
    {
        $name = trim(collect([$user->first_name, $user->last_name])->filter()->implode(' '));

        $log = AccessControlLog::query()->create([
            'payload' => [
                'source' => 'set_user_info',
                'user_id' => $user->id,
                'card_number' => $cardNumber,
                'ok' => $result['ok'],
                'message' => $result['message'],
                'device_status' => $result['status'],
                'device_body' => $result['body'],
                'resolved' => [
                    'card_number' => $cardNumber,
                    'user_name' => $name !== '' ? $name : $user->mobile,
                    'user_id' => $user->id,
                ],
            ],
            'source_ip' => $request->ip(),
        ]);

        event(new AccessControlLogReceived($log));
    }
}
