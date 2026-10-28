<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            $mobile = (string) $request->input('mobile');

            return Limit::perMinute(5)->by($mobile.'|'.$request->ip());
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $mobile = (string) $request->input('mobile');

            return Limit::perMinute(10)->by($mobile.'|'.$request->ip());
        });

        RateLimiter::for('staff-login', function (Request $request) {
            $mobile = (string) $request->input('mobile');

            return Limit::perMinute(5)->by($mobile.'|'.$request->ip());
        });
    }
}
