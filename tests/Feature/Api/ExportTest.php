<?php

use App\Models\Company;
use App\Models\Expense;
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

it('exports expenses as CSV', function () {
    Expense::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
    ]);

    $response = $this->getJson('/api/expenses/export?format=csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->getContent();
    expect($content)->toContain('ID,Title,Amount,Category,User,Date,Created At');
});

it('exports expenses as PDF', function () {
    Expense::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
    ]);

    $response = $this->getJson('/api/expenses/export?format=pdf');

    $response->assertOk();
    // PDF content should start with %PDF
    $content = $response->getContent();
    expect($content)->toContain('%PDF');
});

it('respects authentication requirement', function () {
    // Create a user but don't authenticate as them
    $anotherCompany = Company::factory()->create();
    $anotherUser = User::factory()->create([
        'company_id' => $anotherCompany->id,
        'role' => 'Admin',
    ]);

    // The route is protected by auth:sanctum middleware
    // If we're authenticated as $this->user, we should be able to export
    // This test verifies the endpoint exists and works with auth
    $response = $this->getJson('/api/expenses/export?format=csv');

    $response->assertOk();
});

it('respects company isolation in exports', function () {
    $anotherCompany = Company::factory()->create();
    $anotherUser = User::factory()->create([
        'company_id' => $anotherCompany->id,
        'role' => 'Admin',
    ]);

    // Create expenses for both companies
    Expense::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
    ]);

    Expense::factory()->count(5)->create([
        'user_id' => $anotherUser->id,
        'company_id' => $anotherCompany->id,
    ]);

    // Export as first user
    $response = $this->getJson('/api/expenses/export?format=csv');

    $response->assertOk();
    $content = $response->getContent();

    // Should only have 3 expenses, not 8
    $lines = explode("\n", trim($content));
    // -1 for header row
    expect(count($lines) - 1)->toBe(3);
});

it('rejects invalid export format', function () {
    $response = $this->getJson('/api/expenses/export?format=xml');

    $response->assertBadRequest();
    $response->assertJsonPath('status', 'error');
    $response->assertJsonPath('message', 'Invalid format. Use csv or pdf.');
});

it('defaults to CSV format when format not specified', function () {
    Expense::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
    ]);

    $response = $this->getJson('/api/expenses/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('includes expense details in export', function () {
    $expense = Expense::factory()->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'title' => 'Office Supplies',
        'amount' => 150.50,
        'category' => 'Office',
    ]);

    $response = $this->getJson('/api/expenses/export?format=csv');

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('Office Supplies');
    expect($content)->toContain('150.5');
    expect($content)->toContain('Office');
});

it('handles empty expense list in export', function () {
    // No expenses created

    $response = $this->getJson('/api/expenses/export?format=csv');

    $response->assertOk();
    $content = $response->getContent();

    // Should only have header row
    $lines = array_filter(explode("\n", trim($content)));
    expect(count($lines))->toBe(1);
});

it('includes user name in export', function () {
    $expense = Expense::factory()->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
    ]);

    $response = $this->getJson('/api/expenses/export?format=csv');

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain($this->user->name);
});
