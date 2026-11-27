<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Mail\ResetPasswordMail;
use App\Models\Company;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Notifications\SendEmailVerification;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($request->only(['email', 'password']))) {
            return $this->unauthorized('Invalid credentials');
        }

        $user = User::find(Auth::id());

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Login successful');
    }

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, &$user, &$token, &$verificationToken) {
            $company = Company::create([
                'name' => $validated['company_name'],
                'email' => $validated['company_email'],
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'Admin',
                'company_id' => $company->id,
            ]);

            $token = $user->createToken('accessToken')->plainTextToken;

            // Create email verification token
            $verificationToken = EmailVerificationToken::create([
                'user_id' => $user->id,
                'token' => Str::random(64),
                'expires_at' => now()->addDay(),
            ]);
        });

        // Send verification email (after transaction)
        $user->notify(new SendEmailVerification($verificationToken));

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Admin user for the company registered successfully. Please check your email to verify your account.', 201);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->validated('email');

        $token = Str::random(64);
        $expiresAt = now()->addHour();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => $token, 'created_at' => $expiresAt]
        );

        Mail::to($email)->send(new ResetPasswordMail($token, $email));

        return $this->success(null, 'Password reset link has been sent to your email address.', 202);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $validated = $request->validated();
        $email = $validated['email'];
        $token = $validated['token'];
        $password = $validated['password'];

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (! $resetRecord) {
            return $this->error('Invalid or expired reset token.', 400);
        }

        $createdAt = \Carbon\Carbon::parse($resetRecord->created_at);
        if ($createdAt->addHour()->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return $this->error('Password reset token has expired. Please request a new one.', 410);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return $this->error('User not found.', 404);
        }

        $user->update(['password' => Hash::make($password)]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return $this->success(null, 'Password has been reset successfully.');
    }
}
