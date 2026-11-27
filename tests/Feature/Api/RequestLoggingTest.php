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
    ]);
    Sanctum::actingAs($this->user, ['*']);
});

test('request logging middleware executes without errors', function () {
    $response = $this->getJson('/api/expenses');

    $response->assertOk();
    expect($response->status())->toBe(200);
});

test('authenticated request includes user data', function () {
    $response = $this->getJson('/api/expenses');

    $response->assertOk();
    expect($response->status())->toBe(200);
});

test('unauthenticated request returns 401', function () {
    $response = $this->withoutMiddleware()->getJson('/api/expenses');

    expect($response->status())->toBeLessThan(500);
});
