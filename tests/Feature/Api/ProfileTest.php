<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'role' => 'Admin',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
    ]);
    Sanctum::actingAs($this->user, ['*']);
});

test('authenticated user can retrieve their profile', function () {
    $response = $this->getJson('/api/profile');

    $response->assertOk();
    expect($response['data']['id'])->toBe($this->user->id);
    expect($response['data']['email'])->toBe($this->user->email);
    expect($response['data']['name'])->toBe($this->user->name);
});

test('user can update their profile name only', function () {
    $response = $this->putJson('/api/profile', [
        'name' => 'Updated Name',
        'email' => $this->user->email,
    ]);

    $response->assertOk();
    expect($response['data']['name'])->toBe('Updated Name');

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'Updated Name',
    ]);
});

test('profile update requires name and email', function () {
    $response = $this->putJson('/api/profile', []);

    $response->assertUnprocessable();
    expect($response['errors'])->toHaveKeys(['name', 'email']);
});

test('profile update email must be valid', function () {
    $response = $this->putJson('/api/profile', [
        'name' => 'Updated Name',
        'email' => 'invalid-email',
    ]);

    $response->assertUnprocessable();
    expect($response['errors']['email'])->toHaveCount(1);
});

test('user cannot use duplicate email', function () {
    $anotherUser = User::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'taken@example.com',
    ]);

    $response = $this->putJson('/api/profile', [
        'name' => 'Updated Name',
        'email' => 'taken@example.com',
    ]);

    $response->assertUnprocessable();
    expect($response['errors']['email'][0])->toContain('already registered');
});

test('user can change password', function () {
    $response = $this->postJson('/api/change-password', [
        'current_password' => 'password123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ]);

    $response->assertOk();

    $this->user->refresh();
    $this->assertTrue(
        \Illuminate\Support\Facades\Hash::check('newpassword456', $this->user->password)
    );
});

test('password change requires correct current password', function () {
    $response = $this->postJson('/api/change-password', [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ]);

    $response->assertStatus(422);
    expect($response['message'])->toBe('Current password is incorrect');
});

test('password change requires password confirmation', function () {
    $response = $this->postJson('/api/change-password', [
        'current_password' => 'password123',
        'password' => 'newpassword456',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertUnprocessable();
    expect($response['errors']['password'])->toHaveCount(1);
});

test('password must be at least 8 characters', function () {
    $response = $this->postJson('/api/change-password', [
        'current_password' => 'password123',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertUnprocessable();
    expect($response['errors']['password'])->toHaveCount(1);
});

test('profile update creates audit log', function () {
    $response = $this->putJson('/api/profile', [
        'name' => 'Another Name',
        'email' => $this->user->email,
    ]);

    $response->assertOk();

    $logs = \App\Models\AuditLog::where('user_id', $this->user->id)->get();
    $updateLog = $logs->first(fn ($log) => $log->action === 'updated');

    expect($updateLog)->not->toBeNull();
    expect($updateLog->changes['difference']['name']['new'])->toBe('Another Name');
});

test('password change creates audit log', function () {
    $this->postJson('/api/change-password', [
        'current_password' => 'password123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ]);

    $log = \App\Models\AuditLog::where('user_id', $this->user->id)
        ->where('action', 'password_changed')
        ->first();

    expect($log)->not->toBeNull();
});
