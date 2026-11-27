<?php

use App\Enums\ExpenseStatus;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['currency' => 'USD']);
        $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'Manager']);
        $this->admin = User::factory()->create(['company_id' => $this->company->id, 'role' => 'Admin']);
        $this->employee = User::factory()->create(['company_id' => $this->company->id, 'role' => 'Employee']);

        for ($i = 0; $i < 5; $i++) {
            Expense::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->employee->id,
                'amount' => 100 + ($i * 10),
                'category' => ['Travel', 'Office', 'Meals', 'Software', 'Other'][$i % 5],
                'status' => [ExpenseStatus::Pending, ExpenseStatus::Approved, ExpenseStatus::Rejected][$i % 3],
            ]);
        }
    }

    public function test_manager_can_access_dashboard_stats(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'period',
                'expenses' => ['total_count', 'total_amount', 'average_amount', 'currency'],
                'users',
                'status_breakdown',
            ],
        ]);
    }

    public function test_admin_can_access_dashboard_stats(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertSuccessful();
        $response->assertJsonStructure(['data' => ['expenses', 'users', 'status_breakdown']]);
    }

    public function test_employee_cannot_access_dashboard_stats(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertForbidden();
    }

    public function test_dashboard_stats_includes_correct_expense_count(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertJsonPath('data.expenses.total_count', 5);
    }

    public function test_dashboard_stats_calculates_total_amount_correctly(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/stats');

        $expectedTotal = 100 + 110 + 120 + 130 + 140;
        $response->assertJsonPath('data.expenses.total_amount', '600.00');
    }

    public function test_dashboard_stats_calculates_average_amount(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertJsonPath('data.expenses.average_amount', '120.00');
    }

    public function test_dashboard_stats_includes_user_counts(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertJsonPath('data.users.total_count', 3);
    }

    public function test_dashboard_stats_respects_date_range(): void
    {
        Sanctum::actingAs($this->manager);

        $startDate = now()->subDays(2)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson("/api/dashboard/stats?start_date={$startDate}&end_date={$endDate}");

        $response->assertSuccessful();
        $response->assertJsonPath('data.period.start_date', $startDate);
        $response->assertJsonPath('data.period.end_date', $endDate);
    }

    public function test_spending_by_category_endpoint(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/spending-by-category');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                'period',
                'categories' => [
                    '*' => ['category', 'count', 'total', 'percentage'],
                ],
            ],
        ]);
    }

    public function test_spending_by_category_sorted_by_total(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/spending-by-category');

        $categories = $response->json('data.categories');
        $totals = array_map(function ($cat) {
            return (float) $cat['total'];
        }, $categories);

        for ($i = 0; $i < count($totals) - 1; $i++) {
            expect($totals[$i])->toBeGreaterThanOrEqual($totals[$i + 1]);
        }
    }

    public function test_top_expenses_endpoint(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/top-expenses');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                'period',
                'top_expenses' => [
                    '*' => ['id', 'title', 'amount', 'currency', 'category', 'status', 'user', 'created_at'],
                ],
            ],
        ]);
    }

    public function test_top_expenses_respects_limit_parameter(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/top-expenses?limit=2');

        $expenses = $response->json('data.top_expenses');
        expect(count($expenses))->toBeLessThanOrEqual(2);
    }

    public function test_top_expenses_sorted_by_amount(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/top-expenses');

        $expenses = $response->json('data.top_expenses');
        $amounts = array_map(function ($exp) {
            return (float) $exp['amount'];
        }, $expenses);

        for ($i = 0; $i < count($amounts) - 1; $i++) {
            expect($amounts[$i])->toBeGreaterThanOrEqual($amounts[$i + 1]);
        }
    }

    public function test_trends_endpoint_daily_grouping(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/trends?group_by=daily');

        $response->assertSuccessful();
        $response->assertJsonPath('data.group_by', 'daily');
        $response->assertJsonStructure([
            'data' => [
                'period',
                'group_by',
                'trends' => [
                    '*' => ['date', 'count', 'total'],
                ],
            ],
        ]);
    }

    public function test_trends_endpoint_weekly_grouping(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/trends?group_by=weekly');

        $response->assertSuccessful();
        $response->assertJsonPath('data.group_by', 'weekly');
        $response->assertJsonStructure([
            'data' => [
                'trends' => [
                    '*' => ['week', 'start_date', 'end_date', 'count', 'total'],
                ],
            ],
        ]);
    }

    public function test_trends_endpoint_monthly_grouping(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/trends?group_by=monthly');

        $response->assertSuccessful();
        $response->assertJsonPath('data.group_by', 'monthly');
        $response->assertJsonStructure([
            'data' => [
                'trends' => [
                    '*' => ['month', 'start_date', 'end_date', 'count', 'total'],
                ],
            ],
        ]);
    }

    public function test_user_activity_endpoint(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/user-activity');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                'period',
                'users' => [
                    '*' => ['user_id', 'name', 'email', 'role', 'expense_count', 'total_amount', 'average_amount'],
                ],
            ],
        ]);
    }

    public function test_user_activity_sorted_by_total_amount(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/user-activity');

        $users = $response->json('data.users');
        $amounts = array_map(function ($user) {
            return (float) $user['total_amount'];
        }, $users);

        for ($i = 0; $i < count($amounts) - 1; $i++) {
            expect($amounts[$i])->toBeGreaterThanOrEqual($amounts[$i + 1]);
        }
    }

    public function test_dashboard_respects_company_isolation(): void
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id, 'role' => 'Admin']);

        Expense::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'amount' => 500,
        ]);

        Sanctum::actingAs($this->manager);
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertJsonPath('data.expenses.total_count', 5);
        $response->assertJsonPath('data.expenses.total_amount', '600.00');
    }

    public function test_dashboard_stats_includes_status_breakdown(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/stats');

        $breakdown = $response->json('data.status_breakdown');
        expect($breakdown)->toHaveKey('pending');
        expect($breakdown)->toHaveKey('approved');
        expect($breakdown)->toHaveKey('rejected');
    }

    public function test_dashboard_stats_requires_authentication(): void
    {
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertUnauthorized();
    }

    public function test_spending_by_category_includes_percentages(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/spending-by-category');

        $categories = $response->json('data.categories');
        foreach ($categories as $category) {
            expect((float) $category['percentage'])->toBeGreaterThan(0);
        }
    }

    public function test_top_expenses_includes_user_name(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/top-expenses');

        $expenses = $response->json('data.top_expenses');
        foreach ($expenses as $expense) {
            expect($expense['user'])->not->toBeNull();
            expect($expense['user'])->not->toEqual('');
        }
    }

    public function test_trends_default_grouping_is_daily(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/trends');

        $response->assertJsonPath('data.group_by', 'daily');
    }

    public function test_dashboard_stats_default_date_range_is_last_month(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/dashboard/stats');

        $period = $response->json('data.period');
        $endDate = now()->toDateString();
        $startDate = now()->subMonth()->toDateString();

        expect($period['end_date'])->toBe($endDate);
        expect($period['start_date'])->toBe($startDate);
    }
}
