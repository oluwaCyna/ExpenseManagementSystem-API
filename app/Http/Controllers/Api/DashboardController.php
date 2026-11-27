<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function stats(Request $request)
    {
        if (! in_array(auth()->user()->role, ['Admin', 'Manager'])) {
            return $this->error('Forbidden', 403);
        }

        $companyId = auth()->user()->company_id;
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : now()->subMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : now();

        $expenses = Expense::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalExpenses = $expenses->count();
        $totalAmount = $expenses->sum('amount');
        $averageAmount = $totalExpenses > 0 ? $totalAmount / $totalExpenses : 0;

        $stats = [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'expenses' => [
                'total_count' => $totalExpenses,
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'average_amount' => number_format($averageAmount, 2, '.', ''),
                'currency' => auth()->user()->company->currency,
            ],
            'users' => [
                'total_count' => User::where('company_id', $companyId)->count(),
                'active_count' => User::where('company_id', $companyId)->where('email_verified_at', '!=', null)->count(),
            ],
            'status_breakdown' => $this->getStatusBreakdown($companyId, $startDate, $endDate),
        ];

        return $this->success($stats, 'Dashboard statistics retrieved successfully');
    }

    public function spendingByCategory(Request $request)
    {
        if (! in_array(auth()->user()->role, ['Admin', 'Manager'])) {
            return $this->error('Forbidden', 403);
        }

        $companyId = auth()->user()->company_id;
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : now()->subMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : now();

        $breakdown = Expense::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('category')
            ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'count' => $item->count,
                    'total' => number_format($item->total, 2, '.', ''),
                    'percentage' => $this->calculatePercentage($item->total),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return $this->success([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'categories' => $breakdown,
        ], 'Spending by category retrieved successfully');
    }

    public function topExpenses(Request $request)
    {
        if (! in_array(auth()->user()->role, ['Admin', 'Manager'])) {
            return $this->error('Forbidden', 403);
        }

        $companyId = auth()->user()->company_id;
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : now()->subMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : now();
        $limit = $request->query('limit', 10);

        $expenses = Expense::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('user')
            ->orderBy('amount', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'amount' => number_format($expense->amount, 2, '.', ''),
                    'currency' => $expense->currency,
                    'category' => $expense->category,
                    'status' => $expense->status?->value,
                    'user' => $expense->user->name,
                    'created_at' => $expense->created_at->toDateTimeString(),
                ];
            });

        return $this->success([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'top_expenses' => $expenses,
        ], 'Top expenses retrieved successfully');
    }

    public function trends(Request $request)
    {
        if (! in_array(auth()->user()->role, ['Admin', 'Manager'])) {
            return $this->error('Forbidden', 403);
        }

        $companyId = auth()->user()->company_id;
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : now()->subMonths(6);
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : now();
        $groupBy = $request->query('group_by', 'daily');

        $trends = $this->getTrendData($companyId, $startDate, $endDate, $groupBy);

        return $this->success([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'group_by' => $groupBy,
            'trends' => $trends,
        ], 'Spending trends retrieved successfully');
    }

    public function userActivity(Request $request)
    {
        if (! in_array(auth()->user()->role, ['Admin', 'Manager'])) {
            return $this->error('Forbidden', 403);
        }

        $companyId = auth()->user()->company_id;
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : now()->subMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : now();

        $userActivity = User::where('company_id', $companyId)
            ->withCount(['expenses' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->with(['expenses' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->get()
            ->map(function ($user) {
                $userExpenses = $user->expenses;
                $totalAmount = $userExpenses->sum('amount');

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'expense_count' => $user->expenses_count,
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'average_amount' => $user->expenses_count > 0 ? number_format($totalAmount / $user->expenses_count, 2, '.', '') : '0.00',
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return $this->success([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'users' => $userActivity,
        ], 'User activity retrieved successfully');
    }

    private function getStatusBreakdown($companyId, $startDate, $endDate)
    {
        return Expense::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status?->value ?? 'pending',
                    'count' => $item->count,
                    'total' => number_format($item->total, 2, '.', ''),
                ];
            })
            ->keyBy('status')
            ->toArray();
    }

    private function getTrendData($companyId, $startDate, $endDate, $groupBy)
    {
        $expenses = Expense::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $trends = [];

        if ($groupBy === 'daily') {
            $current = $startDate->clone();
            while ($current <= $endDate) {
                $dayExpenses = $expenses->filter(function ($expense) use ($current) {
                    return $expense->created_at->toDateString() === $current->toDateString();
                });

                $trends[] = [
                    'date' => $current->toDateString(),
                    'count' => $dayExpenses->count(),
                    'total' => number_format($dayExpenses->sum('amount'), 2, '.', ''),
                ];

                $current->addDay();
            }
        } elseif ($groupBy === 'weekly') {
            $current = $startDate->clone()->startOfWeek();
            while ($current <= $endDate) {
                $weekEnd = $current->clone()->endOfWeek();
                $weekExpenses = $expenses->filter(function ($expense) use ($current, $weekEnd) {
                    return $expense->created_at >= $current && $expense->created_at <= $weekEnd;
                });

                $trends[] = [
                    'week' => $current->format('Y-\WW'),
                    'start_date' => $current->toDateString(),
                    'end_date' => $weekEnd->toDateString(),
                    'count' => $weekExpenses->count(),
                    'total' => number_format($weekExpenses->sum('amount'), 2, '.', ''),
                ];

                $current->addWeek();
            }
        } elseif ($groupBy === 'monthly') {
            $current = $startDate->clone()->startOfMonth();
            while ($current <= $endDate) {
                $monthEnd = $current->clone()->endOfMonth();
                $monthExpenses = $expenses->filter(function ($expense) use ($current, $monthEnd) {
                    return $expense->created_at >= $current && $expense->created_at <= $monthEnd;
                });

                $trends[] = [
                    'month' => $current->format('Y-m'),
                    'start_date' => $current->toDateString(),
                    'end_date' => $monthEnd->toDateString(),
                    'count' => $monthExpenses->count(),
                    'total' => number_format($monthExpenses->sum('amount'), 2, '.', ''),
                ];

                $current->addMonth();
            }
        }

        return $trends;
    }

    private function calculatePercentage($amount)
    {
        $companyId = auth()->user()->company_id;
        $total = Expense::where('company_id', $companyId)->sum('amount');

        return $total > 0 ? number_format(($amount / $total) * 100, 1, '.', '') : '0.0';
    }
}
