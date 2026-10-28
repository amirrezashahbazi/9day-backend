<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthenticatesUsers
{
    protected function loginUser(string $mobile = '09121111111'): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $request = $this->postJson('/api/auth/otp/request', ['mobile' => $mobile])->assertOk();

        return $this->postJson('/api/auth/otp/verify', [
            'mobile' => $mobile,
            'code' => $request->json('debug_code'),
        ])->assertOk()->json('token');
    }
}
