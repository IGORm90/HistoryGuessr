<?php

use App\Http\ApiExceptionRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

it('ignores non-API requests', function () {
    $response = (new ApiExceptionRenderer)(new RuntimeException('x'), Request::create('/quizzes'));

    expect($response)->toBeNull();
});

it('includes field errors for validation failures', function () {
    $exception = new ValidationException(Validator::make([], ['year' => 'required']));

    $response = (new ApiExceptionRenderer)($exception, Request::create('/api/v1/game-sessions', 'POST'));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toMatchArray(['code' => 'validation_failed'])
        ->and($response->getData(true)['errors'])->toHaveKey('year');
});

it('maps HTTP exceptions to stable codes', function () {
    $response = (new ApiExceptionRenderer)(new ConflictHttpException('Already answered.'), Request::create('/api/v1/x'));

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true))->toBe(['message' => 'Already answered.', 'code' => 'conflict']);
});

it('hides internal messages when debug is off', function () {
    config(['app.debug' => false]);

    $response = (new ApiExceptionRenderer)(new RuntimeException('secret detail'), Request::create('/api/v1/x'));

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['message'])->toBe('Server error.');
});
