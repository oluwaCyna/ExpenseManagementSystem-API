<?php

use App\Enums\ExpenseStatus;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'Manager']);
        $this->admin = User::factory()->create(['company_id' => $this->company->id, 'role' => 'Admin']);
        $this->employee = User::factory()->create(['company_id' => $this->company->id, 'role' => 'Employee']);
    }

    public function test_manager_can_approve_pending_expense(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/approve", [
            'comment' => 'Looks good!',
        ]);

        $response->assertSuccessful();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Approved);
    }

    public function test_admin_can_approve_pending_expense(): void
    {
        Sanctum::actingAs($this->admin);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/approve", [
            'comment' => 'Approved by admin',
        ]);

        $response->assertSuccessful();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Approved);
    }

    public function test_employee_cannot_approve_expense(): void
    {
        Sanctum::actingAs($this->employee);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/approve", [
            'comment' => 'Trying to approve',
        ]);

        $response->assertForbidden();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Pending);
    }

    public function test_cannot_approve_non_pending_expense(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Approved,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/approve", [
            'comment' => 'Already approved',
        ]);

        $response->assertUnprocessable();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Approved);
    }

    public function test_approve_creates_approval_record_with_comment(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $this->postJson("/api/expenses/{$expense->id}/approve", [
            'comment' => 'Excellent expense report',
        ]);

        $approval = $expense->approvals()->first();
        expect($approval)->not->toBeNull();
        expect($approval->action)->toBe('approved');
        expect($approval->comment)->toBe('Excellent expense report');
        expect($approval->approved_by)->toBe($this->manager->id);
    }

    public function test_can_approve_without_comment(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/approve");

        $response->assertSuccessful();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Approved);
    }

    public function test_manager_can_reject_pending_expense(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Missing receipt',
        ]);

        $response->assertSuccessful();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Rejected);
        expect($expense->rejection_reason)->toBe('Missing receipt');
    }

    public function test_admin_can_reject_pending_expense(): void
    {
        Sanctum::actingAs($this->admin);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Policy violation',
        ]);

        $response->assertSuccessful();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Rejected);
    }

    public function test_employee_cannot_reject_expense(): void
    {
        Sanctum::actingAs($this->employee);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Trying to reject',
        ]);

        $response->assertForbidden();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Pending);
    }

    public function test_rejection_requires_reason(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/reject");

        $response->assertUnprocessable();
    }

    public function test_cannot_reject_non_pending_expense(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Approved,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Already approved',
        ]);

        $response->assertUnprocessable();
    }

    public function test_reject_creates_approval_record(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $this->postJson("/api/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Duplicate expense found',
        ]);

        $approval = $expense->approvals()->first();
        expect($approval)->not->toBeNull();
        expect($approval->action)->toBe('rejected');
        expect($approval->comment)->toBe('Duplicate expense found');
        expect($approval->approved_by)->toBe($this->manager->id);
    }

    public function test_employee_can_resubmit_rejected_expense(): void
    {
        Sanctum::actingAs($this->employee);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'Missing receipt',
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/resubmit", [
            'comment' => 'Receipt now attached',
        ]);

        $response->assertSuccessful();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Pending);
        expect($expense->rejection_reason)->toBeNull();
    }

    public function test_cannot_resubmit_pending_expense(): void
    {
        Sanctum::actingAs($this->employee);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/resubmit");

        $response->assertUnprocessable();
    }

    public function test_non_owner_cannot_resubmit(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'Policy violation',
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/resubmit");

        $response->assertForbidden();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Rejected);
    }

    public function test_resubmit_creates_approval_record(): void
    {
        Sanctum::actingAs($this->employee);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'Missing info',
        ]);

        $this->postJson("/api/expenses/{$expense->id}/resubmit", [
            'comment' => 'Fixed and resubmitted',
        ]);

        $approval = $expense->approvals()->first();
        expect($approval)->not->toBeNull();
        expect($approval->action)->toBe('resubmitted');
        expect($approval->comment)->toBe('Fixed and resubmitted');
        expect($approval->approved_by)->toBe($this->employee->id);
    }

    public function test_can_resubmit_without_comment(): void
    {
        Sanctum::actingAs($this->employee);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'Please revise',
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/resubmit");

        $response->assertSuccessful();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Pending);
    }

    public function test_full_approval_workflow(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
        ]);

        expect($expense->status)->toBe(ExpenseStatus::Pending);

        $this->postJson("/api/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Need more details',
        ]);
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Rejected);

        Sanctum::actingAs($this->employee);
        $this->postJson("/api/expenses/{$expense->id}/resubmit", [
            'comment' => 'Added details',
        ]);
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Pending);

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/expenses/{$expense->id}/approve", [
            'comment' => 'Looks good now',
        ]);
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Approved);

        expect($expense->approvals->count())->toBe(3);
    }

    public function test_expense_resource_includes_status(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Approved,
            'rejection_reason' => null,
        ]);

        $response = $this->getJson('/api/expenses');
        $data = $response->json('data');

        expect($data[0]['status'])->toBe('approved');
        expect($data[0]['rejection_reason'])->toBeNull();
    }

    public function test_expense_resource_includes_rejection_reason(): void
    {
        Sanctum::actingAs($this->manager);

        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'status' => ExpenseStatus::Rejected,
            'rejection_reason' => 'Invalid category',
        ]);

        $response = $this->getJson('/api/expenses');
        $data = $response->json('data');

        expect($data[0]['status'])->toBe('rejected');
        expect($data[0]['rejection_reason'])->toBe('Invalid category');
    }

    public function test_cannot_approve_expense_from_different_company(): void
    {
        Sanctum::actingAs($this->manager);

        $otherCompany = Company::factory()->create();
        $expense = Expense::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => User::factory()->create(['company_id' => $otherCompany->id])->id,
            'status' => ExpenseStatus::Pending,
        ]);

        $response = $this->postJson("/api/expenses/{$expense->id}/approve");

        $response->assertForbidden();
        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::Pending);
    }
}
