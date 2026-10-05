<?php

namespace App\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception on /api/* as the shared envelope: { message, code, errors? }.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message] = match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', $e->getMessage()],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Unauthenticated.'],
            $e instanceof AuthorizationException => [403, 'forbidden', 'This action is unauthorized.'],
            $e instanceof ModelNotFoundException => [404, 'not_found', 'Resource not found.'],
            $e instanceof TokenMismatchException => [419, 'csrf_mismatch', 'CSRF token mismatch.'],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                self::codeForStatus($e->getStatusCode()),
                self::httpMessage($e),
            ],
            default => [500, 'server_error', config('app.debug') ? $e->getMessage() : 'Server error.'],
        };

        $body = ['message' => $message, 'code' => $code];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->errors();
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse($body, $status, $headers);
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            419 => 'csrf_mismatch',
            429 => 'too_many_requests',
            503 => 'service_unavailable',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };
    }

    /** Framework 404 messages contain the requested path, so only explicit 4xx messages pass through. */
    private static function httpMessage(HttpExceptionInterface $e): string
    {
        $status = $e->getStatusCode();

        if ($status === 404 || $status >= 500 || $e->getMessage() === '') {
            return self::defaultMessage($status);
        }

        return $e->getMessage();
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            404 => 'Resource not found.',
            429 => 'Too many requests.',
            503 => 'Service unavailable.',
            default => 'Request failed.',
        };
    }
}
