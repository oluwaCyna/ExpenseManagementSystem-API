<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserListRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CacheService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private CacheService $cacheService) {}

    public function index(UserListRequest $request)
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validated();
        $page = $validated['page'] ?? 1;
        $companyId = auth()->user()->company_id;

        $users = $this->cacheService->getUsersList(
            $companyId,
            $page,
            function () {
                return User::authCompany()->latest()->paginate(10);
            }
        );

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $admin = Auth::user();

        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'company_id' => $admin->company_id,
        ]);

        return $this->success(new UserResource($user), 'User created successfully', 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        $user->update($validated);

        return $this->success(new UserResource($user), 'User role updated successfully');
    }
}
