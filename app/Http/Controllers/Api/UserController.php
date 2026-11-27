<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $this->authorize('viewAny', User::class);

        $page = request('page', 1);
        $companyId = auth()->user()->company_id;
        $cacheKey = "users.page.{$page}.company.{$companyId}";

        $users = Cache::remember($cacheKey, now()->addHours(24), function () {
            return User::authCompany()->latest()->paginate(10);
        });

        $tagKey = "users.company.{$companyId}";

        $keys = Cache::get($tagKey, []);
        $keys[] = $cacheKey;

        Cache::put($tagKey, array_unique($keys), now()->addHours(24));

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
