<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_expense(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense = Expense::factory()->for($user)->for($company)->create();

        $this->actingAs($user);

        $this->deleteJson("/api/expenses/{$expense->id}")
            ->assertSuccessful();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->assertNotNull($expense->fresh()->deleted_at);
        $this->assertNotNull(Expense::withTrashed()->find($expense->id)->deleted_at);
    }

    public function test_soft_deleted_expense_not_in_index(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense1 = Expense::factory()->for($user)->for($company)->create(['title' => 'Active']);
        $expense2 = Expense::factory()->for($user)->for($company)->create(['title' => 'Deleted']);

        $this->actingAs($user);

        $this->deleteJson("/api/expenses/{$expense2->id}");

        $response = $this->getJson('/api/expenses');

        $response->assertSuccessful();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active', $response->json('data.0.title'));
    }

    public function test_restore_soft_deleted_expense(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense = Expense::factory()->for($user)->for($company)->create();

        $this->actingAs($user);

        $this->deleteJson("/api/expenses/{$expense->id}");

        $response = $this->postJson("/api/expenses/{$expense->id}/restore");

        $response->assertSuccessful();
        $this->assertNull($expense->fresh()->deleted_at);
    }

    public function test_restored_expense_appears_in_index(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense = Expense::factory()->for($user)->for($company)->create();

        $this->actingAs($user);

        $this->deleteJson("/api/expenses/{$expense->id}");
        $this->postJson("/api/expenses/{$expense->id}/restore");

        $response = $this->getJson('/api/expenses');

        $response->assertSuccessful();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_only_admin_can_restore_expense(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->for($company)->create(['role' => 'Admin']);
        $employee = User::factory()->for($company)->create(['role' => 'Employee']);
        $expense = Expense::factory()->for($admin)->for($company)->create();

        $this->actingAs($admin)->deleteJson("/api/expenses/{$expense->id}");

        $this->actingAs($employee)
            ->postJson("/api/expenses/{$expense->id}/restore")
            ->assertForbidden();

        $this->assertNotNull(Expense::withTrashed()->find($expense->id)->deleted_at);
    }

    public function test_soft_delete_user(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_soft_deleted_user_not_in_index(): void
    {
        $company = Company::factory()->create();
        $user1 = User::factory()->for($company)->create(['name' => 'Active User', 'role' => 'Admin']);
        $user2 = User::factory()->for($company)->create(['name' => 'Deleted User']);
        $admin = User::factory()->for($company)->create(['role' => 'Admin']);

        $user2->delete();

        $this->actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertSuccessful();
        $this->assertCount(2, $response->json('data'));
        $names = collect($response->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Deleted User'));
    }

    public function test_soft_delete_company(): void
    {
        $company = Company::factory()->create();

        $company->delete();

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_soft_deleted_records_with_trashed(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $user->delete();

        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
        $this->assertNotNull(User::onlyTrashed()->find($user->id));
    }

    public function test_multiple_soft_deletes(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense1 = Expense::factory()->for($user)->for($company)->create();
        $expense2 = Expense::factory()->for($user)->for($company)->create();
        $expense3 = Expense::factory()->for($user)->for($company)->create();

        $this->actingAs($user);

        $this->deleteJson("/api/expenses/{$expense1->id}");
        $this->deleteJson("/api/expenses/{$expense2->id}");

        $this->assertCount(1, Expense::all());
        $this->assertCount(3, Expense::withTrashed()->get());
        $this->assertCount(2, Expense::onlyTrashed()->get());
    }

    public function test_soft_delete_audit_log_created(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense = Expense::factory()->for($user)->for($company)->create();

        $this->actingAs($user);

        $this->deleteJson("/api/expenses/{$expense->id}");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_cannot_access_soft_deleted_expense_directly(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense = Expense::factory()->for($user)->for($company)->create();

        $this->actingAs($user);

        $this->deleteJson("/api/expenses/{$expense->id}");

        $this->putJson("/api/expenses/{$expense->id}", ['title' => 'Updated'])
            ->assertNotFound();
    }

    public function test_expense_cache_cleared_on_soft_delete(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => 'Admin']);
        $expense = Expense::factory()->for($user)->for($company)->create();

        $this->actingAs($user);

        // Prime the cache
        $this->getJson('/api/expenses');

        // Delete the expense
        $this->deleteJson("/api/expenses/{$expense->id}");

        // Verify expense not in fresh query
        $response = $this->getJson('/api/expenses');
        $this->assertCount(0, $response->json('data'));
    }
}
