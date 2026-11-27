<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseListRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\CacheService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    use ApiResponse;

    public function __construct(private CacheService $cacheService) {}

    public function index(ExpenseListRequest $request)
    {
        $companyId = auth()->user()->company_id;
        $validated = $request->validated();
        $page = $validated['page'] ?? 1;
        $search = $validated['search'] ?? '';

        $expenses = $this->cacheService->getExpensesList(
            $companyId,
            $page,
            $search,
            function () use ($search) {
                $expenses = Expense::authCompany();

                $expenses->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%$search%")
                        ->orWhere('category', 'like', "%$search%");
                });

                return $expenses->with('user')->latest()->paginate(10);
            }
        );

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request)
    {
        $user = Auth::user();

        $validated = $request->validated();

        $expense = Expense::create([
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'category' => $validated['category'],
            'currency' => $validated['currency'] ?? $user->company->currency,
            'user_id' => $user->id,
            'company_id' => $user->company_id,
        ]);

        return $this->success(new ExpenseResource($expense), 'Expense created successfully', 201);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $validated = $request->validated();

        $expense->update($validated);

        return $this->success(new ExpenseResource($expense), 'Expense updated successfully');
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return $this->success(null, 'Expense deleted successfully');
    }

    public function restore($expenseId)
    {
        $expense = Expense::withTrashed()->findOrFail($expenseId);

        $this->authorize('delete', $expense);

        $expense->restore();

        return $this->success(new ExpenseResource($expense), 'Expense restored successfully');
    }
}
