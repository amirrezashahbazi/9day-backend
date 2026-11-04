<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AccessControlDeviceClient
{
    /**
     * @return array{ok: bool, message: string, status: int|null, body: mixed}
     */
    public function setUserInfo(User $user, string $cardNumber): array
    {
        $serial = AppSetting::getString('access_control_device_serial');
        if ($serial === '') {
            return [
                'ok' => false,
                'message' => 'شماره سریال دستگاه در تنظیمات سایت ثبت نشده است.',
                'status' => null,
                'body' => null,
            ];
        }

        $username = trim(collect([$user->first_name, $user->last_name])->filter()->implode(' '));
        if ($username === '') {
            $username = $user->mobile;
        }

        $payload = [
            'serialNumber' => $serial,
            'methodName' => '',
            'data' => [
                'enrollid' => $cardNumber,
                'username' => $username,
                'backupnum' => (int) config('services.access_control_device.backupnum', 11),
                'admin' => 0,
                'carddata' => $cardNumber,
                'fpdata' => 'string',
            ],
        ];

        $url = rtrim((string) config('services.access_control_device.base_url'), '/')
            .(string) config('services.access_control_device.set_user_path');
        $timeout = max(5, (int) config('services.access_control_device.timeout', 60));

        try {
            $response = Http::accept('text/plain')
                ->asJson()
                ->timeout($timeout)
                ->connectTimeout(15)
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Access control device connection failed', [
                'user_id' => $user->id,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'اتصال به دستگاه کنترل تردد برقرار نشد. کمی بعد دوباره تلاش کنید.',
                'status' => null,
                'body' => null,
            ];
        } catch (\Throwable $exception) {
            Log::error('Access control device request failed', [
                'user_id' => $user->id,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('خطا در ارتباط با دستگاه کنترل تردد.', 0, $exception);
        }

        $body = $this->decodeBody($response->body());
        $ok = $response->successful() && $this->looksSuccessful($body);

        Log::info('Access control device SetUserInfo response', [
            'user_id' => $user->id,
            'status' => $response->status(),
            'ok' => $ok,
            'body' => $body,
        ]);

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'کاربر روی دستگاه کنترل تردد ثبت شد.'
                : ($this->errorMessageFromBody($body) ?: 'دستگاه کنترل تردد پاسخ موفق برنگرداند.'),
            'status' => $response->status(),
            'body' => $body,
        ];
    }

    private function decodeBody(string $raw): mixed
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $json = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE ? $json : $trimmed;
    }

    private function looksSuccessful(mixed $body): bool
    {
        if (! is_array($body)) {
            if (is_string($body)) {
                $lower = mb_strtolower($body);

                return ! str_contains($lower, 'error')
                    && ! str_contains($lower, 'fail')
                    && ! str_contains($lower, 'خطا');
            }

            return true;
        }

        foreach (['success', 'Success', 'isSuccess', 'IsSuccess'] as $key) {
            if (array_key_exists($key, $body)) {
                return filter_var($body[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (['code', 'Code', 'statusCode', 'StatusCode', 'resultCode', 'ResultCode'] as $key) {
            if (! array_key_exists($key, $body)) {
                continue;
            }
            $code = $body[$key];
            if (is_numeric($code)) {
                return (int) $code === 0 || (int) $code === 200;
            }
            if (is_string($code)) {
                $normalized = strtoupper($code);

                return in_array($normalized, ['0', '200', 'OK', 'SUCCESS', 'TRUE'], true);
            }
        }

        foreach (['message', 'Message', 'msg', 'Msg', 'error', 'Error'] as $key) {
            if (! isset($body[$key]) || ! is_string($body[$key])) {
                continue;
            }
            $lower = mb_strtolower($body[$key]);
            if (str_contains($lower, 'error') || str_contains($lower, 'fail') || str_contains($lower, 'خطا')) {
                return false;
            }
        }

        return true;
    }

    private function errorMessageFromBody(mixed $body): ?string
    {
        if (! is_array($body)) {
            return is_string($body) && $body !== '' ? $body : null;
        }

        foreach (['message', 'Message', 'msg', 'Msg', 'error', 'Error', 'errorMessage', 'ErrorMessage'] as $key) {
            if (isset($body[$key]) && is_string($body[$key]) && $body[$key] !== '') {
                return $body[$key];
            }
        }

        return null;
    }
}
