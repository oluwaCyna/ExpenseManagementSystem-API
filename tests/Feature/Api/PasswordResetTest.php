<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_with_valid_email(): void
    {
        Mail::fake();

        $user = User::factory()->for(Company::factory())->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_forgot_password_with_invalid_email(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nonexistent@example.com',
        ]);
    }

    public function test_forgot_password_with_malformed_email(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = User::factory()->for(Company::factory())->create([
            'email' => 'user@example.com',
            'password' => 'OldPassword123!',
        ]);

        $token = 'valid-reset-token-'.time();
        $expiresAt = now()->addHour();

        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => $token,
            'created_at' => $expiresAt,
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'status' => 'success',
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('NewPassword123!', $user->fresh()->password)
        );
    }

    public function test_reset_password_with_expired_token(): void
    {
        $user = User::factory()->for(Company::factory())->create(['email' => 'user@example.com']);

        $token = 'expired-token';
        $expiresAt = now()->subHour();

        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => $token,
            'created_at' => $expiresAt,
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(410);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_reset_password_with_invalid_token(): void
    {
        $user = User::factory()->for(Company::factory())->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'user@example.com',
            'token' => 'invalid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(400);
    }

    public function test_reset_password_with_mismatched_passwords(): void
    {
        $user = User::factory()->for(Company::factory())->create(['email' => 'user@example.com']);

        $token = 'valid-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => $token,
            'created_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_with_weak_password(): void
    {
        $user = User::factory()->for(Company::factory())->create(['email' => 'user@example.com']);

        $token = 'valid-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => $token,
            'created_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/reset-password', [
            'email' => 'nonexistent@example.com',
            'token' => 'valid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_rate_limiting_on_forgot_password(): void
    {
        $user = User::factory()->for(Company::factory())->create(['email' => 'user@example.com']);

        $response1 = $this->postJson('/api/forgot-password', ['email' => 'user@example.com']);
        $response2 = $this->postJson('/api/forgot-password', ['email' => 'user@example.com']);
        $response3 = $this->postJson('/api/forgot-password', ['email' => 'user@example.com']);
        $response4 = $this->postJson('/api/forgot-password', ['email' => 'user@example.com']);
        $response5 = $this->postJson('/api/forgot-password', ['email' => 'user@example.com']);
        $response6 = $this->postJson('/api/forgot-password', ['email' => 'user@example.com']);

        $response6->assertStatus(429);
    }
}
