<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            if ($exception instanceof ValidationException) {
                return $this->validationError($exception->errors(), 'Validation error');
            }

            if ($exception instanceof NotFoundHttpException) {
                return $this->notFound();
            }

            if ($exception instanceof MethodNotAllowedHttpException) {
                return $this->error('The requested method is not allowed.', 405);
            }

            if ($exception instanceof TooManyRequestsHttpException) {
                return $this->error('Too Many Attempts.', 429);
            }

            if ($exception instanceof AuthorizationException) {
                return $this->forbidden();
            }

            return $this->handleUncaughtException($exception);
        }

        return parent::render($request, $exception);
    }

    private function handleUncaughtException(Throwable $exception): \Illuminate\Http\JsonResponse
    {
        $status = 500;
        $message = 'An error occurred';

        if (config('app.debug')) {
            $message = $exception->getMessage() ?: 'An error occurred';
        }

        return $this->error($message, $status);
    }
}
