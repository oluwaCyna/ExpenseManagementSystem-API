<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResendEmailVerificationRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Notifications\SendEmailVerification;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    public function verify(VerifyEmailRequest $request)
    {
        $token = EmailVerificationToken::query()
            ->where('token', $request->validated('token'))
            ->firstOrFail();

        if ($token->isExpired()) {
            $token->delete();

            return $this->error('Verification token has expired. Please request a new one.', 410);
        }

        $user = $token->user;

        if ($user->email !== $request->validated('email')) {
            return $this->error('Email does not match the token.', 400);
        }

        if ($user->email_verified_at) {
            return $this->success(null, 'Email already verified.');
        }

        DB::transaction(function () use ($user, $token) {
            $user->update(['email_verified_at' => now()]);
            $token->delete();
        });

        return $this->success(['verified' => true], 'Email verified successfully.');
    }

    public function resend(ResendEmailVerificationRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        if ($user->email_verified_at) {
            return $this->success(null, 'Email is already verified.');
        }

        // Delete existing tokens for this user
        EmailVerificationToken::where('user_id', $user->id)->delete();

        // Create new verification token
        $token = EmailVerificationToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDay(),
        ]);

        // Send verification email
        $user->notify(new SendEmailVerification($token));

        return $this->success(null, 'Verification email sent. Please check your inbox.', 202);
    }
}
