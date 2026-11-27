<?php

namespace Tests\Feature\Api;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_verification_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'Test Company',
            'company_email' => 'company@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => User::where('email', 'john@example.com')->first()->id,
        ]);
    }

    public function test_verify_email_with_valid_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = EmailVerificationToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/verify-email', [
            'token' => $token->token,
            'email' => $user->email,
        ]);

        $response->assertSuccessful();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_tokens', ['id' => $token->id]);
    }

    public function test_verify_email_with_expired_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = EmailVerificationToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/verify-email', [
            'token' => $token->token,
            'email' => $user->email,
        ]);

        $response->assertStatus(410);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_tokens', ['id' => $token->id]);
    }

    public function test_verify_email_with_invalid_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->postJson('/api/verify-email', [
            'token' => 'invalid-token',
            'email' => $user->email,
        ]);

        $response->assertStatus(422);
    }

    public function test_verify_email_with_mismatched_email(): void
    {
        $user1 = User::factory()->create(['email_verified_at' => null]);
        $user2 = User::factory()->create(['email_verified_at' => null]);
        $token = EmailVerificationToken::factory()->create([
            'user_id' => $user1->id,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/verify-email', [
            'token' => $token->token,
            'email' => $user2->email,
        ]);

        $response->assertStatus(400);
    }

    public function test_verify_already_verified_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = EmailVerificationToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/verify-email', [
            'token' => $token->token,
            'email' => $user->email,
        ]);

        $response->assertSuccessful();
    }

    public function test_resend_verification_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->postJson('/api/resend-verification-email', [
            'email' => $user->email,
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $user->id]);
    }

    public function test_resend_to_already_verified_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->postJson('/api/resend-verification-email', [
            'email' => $user->email,
        ]);

        $response->assertSuccessful();
    }

    public function test_resend_to_nonexistent_email(): void
    {
        $response = $this->postJson('/api/resend-verification-email', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_resend_deletes_old_tokens(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $oldToken = EmailVerificationToken::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/resend-verification-email', [
            'email' => $user->email,
        ]);

        $this->assertDatabaseMissing('email_verification_tokens', ['id' => $oldToken->id]);
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $user->id]);
    }
}
