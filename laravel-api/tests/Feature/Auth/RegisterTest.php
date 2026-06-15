<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $payload)
    {
        return $this->postJson('/api/shop/auth/register', $payload);
    }

    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $override);
    }

    /** AC-03.1 */
    public function test_customer_can_register(): void
    {
        $res = $this->register($this->valid())
            ->assertCreated()
            ->assertJsonStructure(['access_token', 'type'])
            ->assertJson(['type' => 'customer']);

        $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
        $customer = Customer::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame(UserStatus::ACTIVE, $customer->status);
        $this->assertTrue(Hash::check('secret123', $customer->password));

        // token dùng được trên Shop
        $this->withToken($res->json('access_token'))->getJson('/api/shop/ping')->assertOk();
    }

    /** AC-03.2 */
    public function test_duplicate_email_is_rejected(): void
    {
        Customer::factory()->create(['email' => 'jane@example.com']);

        $this->register($this->valid())
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /** AC-03.3 — password tối thiểu 8, có chữ + số */
    public function test_too_short_password_is_rejected(): void
    {
        $this->register($this->valid(['password' => 'ab1', 'password_confirmation' => 'ab1']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_password_without_number_is_rejected(): void
    {
        $this->register($this->valid(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    /** AC-03.4 */
    public function test_password_confirmation_must_match(): void
    {
        $this->register($this->valid(['password_confirmation' => 'different1']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    /** AC-03.5 — không thể tự đăng ký employee/admin; field đặc quyền bị bỏ qua */
    public function test_cannot_self_register_as_employee_or_admin(): void
    {
        $this->register($this->valid(['type' => 'ADMIN', 'role' => 'admin']))
            ->assertCreated();

        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
    }

    public function test_validation_requires_core_fields(): void
    {
        $this->register([])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
