<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_persian_mobile_is_rejected(): void
    {
        $this->postJson('/api/auth/otp/request', [
            'mobile' => '۰۹۱۲۱۱۱۱۱۱۱',
        ])->assertStatus(422)
            ->assertJsonFragment(['ارقام باید انگلیسی باشند.']);
    }

    public function test_otp_is_issued_and_logged(): void
    {
        $mobile = '09121111111';

        $this->postJson('/api/auth/otp/request', ['mobile' => $mobile])
            ->assertOk()
            ->assertJsonStructure(['debug_code', 'expires_in']);

        $log = collect(glob(storage_path('logs/otp*.log')))->last();
        $this->assertNotNull($log);
        $this->assertStringContainsString($mobile, (string) file_get_contents($log));
    }

    public function test_verify_creates_user_and_second_login_reuses_it(): void
    {
        $mobile = '09122222222';
        $code = $this->postJson('/api/auth/otp/request', ['mobile' => $mobile])->json('debug_code');

        $first = $this->postJson('/api/auth/otp/verify', [
            'mobile' => $mobile,
            'code' => $code,
        ])->assertOk();

        $this->assertSame(1, User::query()->where('mobile', $mobile)->count());
        $this->assertNotEmpty($first->json('user.file_number'));

        $this->travel(61)->seconds();
        $next = $this->postJson('/api/auth/otp/request', ['mobile' => $mobile])->json('debug_code');
        $second = $this->postJson('/api/auth/otp/verify', [
            'mobile' => $mobile,
            'code' => $next,
        ])->assertOk();

        $this->assertSame($first->json('user.id'), $second->json('user.id'));
        $this->travelBack();
    }

    public function test_wrong_code_is_rejected(): void
    {
        $mobile = '09123333333';
        $this->postJson('/api/auth/otp/request', ['mobile' => $mobile])->assertOk();

        $this->postJson('/api/auth/otp/verify', [
            'mobile' => $mobile,
            'code' => '000000',
        ])->assertStatus(422);
    }

    public function test_staff_can_log_in_with_otp(): void
    {
        $admin = User::factory()->admin()->create(['mobile' => '09124444444']);
        $code = $this->postJson('/api/auth/otp/request', ['mobile' => '09124444444'])
            ->assertOk()
            ->json('debug_code');

        $this->postJson('/api/auth/otp/verify', [
            'mobile' => '09124444444',
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.role', 'admin');
    }
}
