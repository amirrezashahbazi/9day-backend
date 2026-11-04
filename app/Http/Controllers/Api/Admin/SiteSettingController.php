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
            'access_control_display_ms' => $this->displayMs(),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        AppSetting::putValue(
            'access_control_device_serial',
            $request->string('access_control_device_serial')->toString(),
        );

        $ms = min(10000, max(200, (int) $request->integer('access_control_display_ms')));
        AppSetting::putValue('access_control_display_ms', (string) $ms);

        return response()->json([
            'message' => 'تنظیمات سایت ذخیره شد.',
            'access_control_device_serial' => AppSetting::getString('access_control_device_serial'),
            'access_control_display_ms' => $this->displayMs(),
        ]);
    }

    private function displayMs(): int
    {
        $raw = AppSetting::query()->where('key', 'access_control_display_ms')->value('value');
        if ($raw === null) {
            return 1000;
        }

        return min(10000, max(200, (int) $raw));
    }
}
