<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\EmailVerificationToken;
use App\Notifications\SendEmailVerification;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(
            new UserResource(auth()->user()),
            'Profile retrieved successfully'
        );
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $changes = [];

        if ($request->name !== $user->name) {
            $changes['name'] = [
                'old' => $user->name,
                'new' => $request->name,
            ];
        }

        if ($request->email !== $user->email) {
            $changes['email'] = [
                'old' => $user->email,
                'new' => $request->email,
            ];
            $user->email_verified_at = null;
        }

        if (! empty($changes)) {
            $user->update($request->only(['name', 'email']));

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'action' => 'updated',
                'model_type' => 'User',
                'model_id' => $user->id,
                'changes' => [
                    'difference' => $changes,
                    'old' => collect($changes)->mapWithKeys(fn ($change, $key) => [$key => $change['old']])->toArray(),
                    'new' => collect($changes)->mapWithKeys(fn ($change, $key) => [$key => $change['new']])->toArray(),
                ],
            ]);

            if (isset($changes['email'])) {
                $verificationToken = EmailVerificationToken::create([
                    'email' => $user->email,
                    'token' => bin2hex(random_bytes(32)),
                    'expires_at' => now()->addDay(),
                ]);

                $user->notify(new SendEmailVerification($verificationToken));
            }

            return $this->success(
                new UserResource($user),
                'Profile updated successfully'
            );
        }

        return $this->success(
            new UserResource($user),
            'No changes made'
        );
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->error(
                'Current password is incorrect',
                422
            );
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'action' => 'password_changed',
            'model_type' => 'User',
            'model_id' => $user->id,
            'changes' => [
                'difference' => ['password' => 'changed'],
                'old' => null,
                'new' => null,
            ],
        ]);

        return $this->success(
            null,
            'Password changed successfully'
        );
    }
}
