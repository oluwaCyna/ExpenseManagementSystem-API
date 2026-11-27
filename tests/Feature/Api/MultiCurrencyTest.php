<?php

use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['currency' => 'USD']);
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'role' => 'Admin',
    ]);
    Sanctum::actingAs($this->user, ['*']);
});

it('stores expense with default company currency when not specified', function () {
    $response = $this->postJson('/api/expenses', [
        'title' => 'Office Supplies',
        'amount' => 150.50,
        'category' => 'Office',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.currency', 'USD');

    $this->assertDatabaseHas('expenses', [
        'currency' => 'USD',
        'title' => 'Office Supplies',
    ]);
});

it('stores expense with specified currency', function () {
    $response = $this->postJson('/api/expenses', [
        'title' => 'European Conference',
        'amount' => 2500,
        'category' => 'Travel',
        'currency' => 'EUR',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.currency', 'EUR');

    $this->assertDatabaseHas('expenses', [
        'currency' => 'EUR',
        'title' => 'European Conference',
    ]);
});

it('validates currency code format', function () {
    $response = $this->postJson('/api/expenses', [
        'title' => 'Test',
        'amount' => 100,
        'category' => 'Office',
        'currency' => 'invalid', // lowercase
    ]);

    $response->assertUnprocessable();
    expect($response['errors']['currency'])->toContain('Currency code must be 3 characters.');
});

it('rejects currency code with wrong length', function () {
    $response = $this->postJson('/api/expenses', [
        'title' => 'Test',
        'amount' => 100,
        'category' => 'Office',
        'currency' => 'USDA', // 4 characters
    ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.currency.0', 'Currency code must be 3 characters.');
});

it('returns currency in expense list', function () {
    Expense::factory()->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'currency' => 'GBP',
    ]);

    $response = $this->getJson('/api/expenses');

    $response->assertOk();
    expect($response['data'][0]['currency'])->toBe('GBP');
});

it('allows updating expense currency', function () {
    $expense = Expense::factory()->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'currency' => 'USD',
    ]);

    $response = $this->putJson("/api/expenses/{$expense->id}", [
        'currency' => 'JPY',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.currency', 'JPY');

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'currency' => 'JPY',
    ]);
});

it('company has default currency', function () {
    $company = Company::factory()->create(['currency' => 'EUR']);

    $response = $this->getJson('/api/users');

    $response->assertOk();
    expect($this->company->currency)->toBe('USD');
    expect($company->currency)->toBe('EUR');
});

it('respects multiple currencies in report', function () {
    $this->postJson('/api/expenses', [
        'title' => 'USD Expense',
        'amount' => 100,
        'category' => 'Office',
        'currency' => 'USD',
    ]);

    $this->postJson('/api/expenses', [
        'title' => 'EUR Expense',
        'amount' => 100,
        'category' => 'Office',
        'currency' => 'EUR',
    ]);

    $this->postJson('/api/expenses', [
        'title' => 'GBP Expense',
        'amount' => 100,
        'category' => 'Office',
        'currency' => 'GBP',
    ]);

    $response = $this->getJson('/api/expenses');

    $response->assertOk();
    expect($response['data'])->toHaveCount(3);

    $currencies = collect($response['data'])->pluck('currency')->toArray();
    expect($currencies)->toContain('USD');
    expect($currencies)->toContain('EUR');
    expect($currencies)->toContain('GBP');
});

it('includes currency in CSV export', function () {
    Expense::factory()->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'currency' => 'EUR',
        'title' => 'European Trip',
        'amount' => 500,
    ]);

    $response = $this->getJson('/api/expenses/export?format=csv');

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->toContain('EUR');
    expect($content)->toContain('European Trip');
});

it('stores currency correctly on update', function () {
    $expense = Expense::factory()->create([
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'title' => 'Original',
        'amount' => 100,
        'currency' => 'USD',
    ]);

    $response = $this->putJson("/api/expenses/{$expense->id}", [
        'title' => 'Updated',
        'amount' => 200,
        'currency' => 'AUD',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.title', 'Updated');
    $response->assertJsonPath('data.amount', 200);
    $response->assertJsonPath('data.currency', 'AUD');
});

it('supports all ISO 4217 currency codes', function () {
    $currencies = ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY', 'INR', 'MXN'];

    foreach ($currencies as $currency) {
        $response = $this->postJson('/api/expenses', [
            'title' => "Test {$currency}",
            'amount' => 100,
            'category' => 'Office',
            'currency' => $currency,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.currency', $currency);
    }
});
