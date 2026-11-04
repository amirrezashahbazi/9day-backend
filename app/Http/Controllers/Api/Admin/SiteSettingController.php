<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class SiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'access_control_device_serial' => AppSetting::getString('access_control_device_serial', 'AYSC01015467'),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        AppSetting::putValue(
            'access_control_device_serial',
            $request->string('access_control_device_serial')->toString(),
        );

        return response()->json([
            'message' => 'تنظیمات سایت ذخیره شد.',
            'access_control_device_serial' => AppSetting::getString('access_control_device_serial'),
        ]);
    }
}
