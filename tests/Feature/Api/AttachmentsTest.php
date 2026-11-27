<?php

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('lists attachments for an expense', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);

    $expense = Expense::factory()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);

    ExpenseAttachment::factory()->count(3)->create(['expense_id' => $expense->id]);

    Sanctum::actingAs($admin, ['*']);
    $response = $this->getJson("/api/expenses/{$expense->id}/attachments");

    $response->assertSuccessful();
    expect($response->json())->toBeTruthy();
    expect(count($response->json()))->toBe(3);
});

it('uploads an attachment to an expense', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);

    $expense = Expense::factory()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);

    Sanctum::actingAs($admin, ['*']);
    $file = UploadedFile::fake()->image('receipt.jpg');

    $response = $this->postJson("/api/expenses/{$expense->id}/attachments", [
        'attachment' => $file,
        'description' => 'Receipt for travel expense',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.filename', 'receipt.jpg');
    $response->assertJsonPath('data.description', 'Receipt for travel expense');

    $this->assertDatabaseHas('expense_attachments', [
        'expense_id' => $expense->id,
        'filename' => 'receipt.jpg',
    ]);
});

it('validates attachment file type', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);

    $expense = Expense::factory()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);

    Sanctum::actingAs($admin, ['*']);
    $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

    $response = $this->postJson("/api/expenses/{$expense->id}/attachments", [
        'attachment' => $file,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.attachment.0', 'File must be an image (JPEG, PNG, JPG) or document (PDF, DOC, DOCX).');
});

it('validates attachment file size', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);

    $expense = Expense::factory()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);

    Sanctum::actingAs($admin, ['*']);
    $file = UploadedFile::fake()->image('large.jpg')->size(6000); // 6MB

    $response = $this->postJson("/api/expenses/{$expense->id}/attachments", [
        'attachment' => $file,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.attachment.0', 'File size cannot exceed 5MB.');
});

it('allows only expense owner and admin to upload attachments', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $employee = User::factory()->create(['role' => 'Employee']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);
    $employee->update(['company_id' => $company->id]);

    $expense = Expense::factory()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);

    Sanctum::actingAs($employee, ['*']);
    $file = UploadedFile::fake()->image('receipt.jpg');

    $response = $this->postJson("/api/expenses/{$expense->id}/attachments", [
        'attachment' => $file,
    ]);

    $response->assertForbidden();
});

it('deletes an attachment', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);

    $expense = Expense::factory()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);

    $attachment = ExpenseAttachment::factory()->create([
        'expense_id' => $expense->id,
        'path' => 'expenses/1/receipt.jpg',
    ]);

    Sanctum::actingAs($admin, ['*']);
    $response = $this->deleteJson("/api/expenses/{$expense->id}/attachments/{$attachment->id}");

    $response->assertSuccessful();

    $this->assertSoftDeleted('expense_attachments', [
        'id' => $attachment->id,
    ]);
});

it('prevents deleting attachment from wrong expense', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);

    $expense1 = Expense::factory()->create(['company_id' => $company->id, 'user_id' => $admin->id]);
    $expense2 = Expense::factory()->create(['company_id' => $company->id, 'user_id' => $admin->id]);

    $attachment = ExpenseAttachment::factory()->create(['expense_id' => $expense1->id]);

    Sanctum::actingAs($admin, ['*']);
    $response = $this->deleteJson("/api/expenses/{$expense2->id}/attachments/{$attachment->id}");

    $response->assertNotFound();
});

it('formats file size correctly', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $company = Company::factory()->create();
    $admin->update(['company_id' => $company->id]);

    $expense = Expense::factory()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);

    $attachment = ExpenseAttachment::factory()->create([
        'expense_id' => $expense->id,
        'file_size' => 1024 * 1024, // 1 MB
    ]);

    expect($attachment->getFileSizeFormatted())->toBe('1 MB');
});
