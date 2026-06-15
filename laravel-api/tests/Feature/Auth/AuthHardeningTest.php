<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config([
            'auth_security.login_max_attempts' => 5,
            'auth_security.login_decay_seconds' => 60,
            'auth_security.lockout_threshold' => 10,
            'sanctum.inactivity_timeout' => 30,
        ]);
    }

    private function login(array $payload)
    {
        return $this->postJson('/api/crm/auth/login', $payload);
    }

    private function employee(array $attrs = []): Employee
    {
        $e = Employee::factory()->create(array_merge(['password' => Hash::make('secret123')], $attrs));
        $e->assignRole('employee');

        return $e;
    }

    /** AC-07.1 — 5 fail/60s → 429 */
    public function test_rate_limit_blocks_after_five_failures(): void
    {
        $this->employee(['email' => 'emp@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->login(['email' => 'emp@example.com', 'password' => 'wrong'])->assertUnauthorized();
        }

        $this->login(['email' => 'emp@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    /** AC-07.5 — 10 fail liên tiếp → LOCKED → 403 dù pass đúng */
    public function test_account_locks_after_threshold_then_forbids_even_with_correct_password(): void
    {
        $emp = $this->employee(['email' => 'emp@example.com']);
        $emp->update(['failed_login_attempts' => 9]);

        // lần fail thứ 10 → khoá tài khoản
        $this->login(['email' => 'emp@example.com', 'password' => 'wrong'])->assertUnauthorized();

        $this->assertSame(UserStatus::LOCKED, $emp->fresh()->status);

        // pass đúng nhưng đã LOCKED → 403
        $this->login(['email' => 'emp@example.com', 'password' => 'secret123'])->assertForbidden();
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $emp = $this->employee(['email' => 'emp@example.com', 'failed_login_attempts' => 3]);

        $this->login(['email' => 'emp@example.com', 'password' => 'secret123'])->assertOk();

        $this->assertSame(0, $emp->fresh()->failed_login_attempts);
    }

    /** AC-07.4 — token quá hạn inactivity → 401 */
    public function test_inactive_token_is_rejected_after_timeout(): void
    {
        $emp = $this->employee();
        $new = $emp->createToken('crm');
        $token = $new->plainTextToken;

        // token mới → dùng được
        $this->withToken($token)->getJson('/api/crm/ping')->assertOk();

        // mô phỏng không hoạt động > 30 phút
        $new->accessToken->forceFill([
            'last_used_at' => now()->subMinutes(31),
            'created_at' => now()->subMinutes(120),
        ])->save();

        // Ép resolve lại token (tránh user đã cache trên guard trong cùng test).
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/crm/ping')->assertUnauthorized();
    }
}
