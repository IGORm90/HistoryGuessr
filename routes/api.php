<?php

use App\Http\Controllers\Api\V1\AdvanceGameController;
use App\Http\Controllers\Api\V1\AnswerController;
use App\Http\Controllers\Api\V1\GameResultController;
use App\Http\Controllers\Api\V1\GameSessionController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Middleware\EnsureAnonymousPlayer;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('quizzes', [QuizController::class, 'index'])->name('quizzes.index');

    Route::middleware(EnsureAnonymousPlayer::class)->prefix('game-sessions')->name('game-sessions.')->group(function () {
        Route::get('{gameSession}', [GameSessionController::class, 'show'])->name('show');
        Route::get('{gameSession}/result', GameResultController::class)->name('result');

        Route::middleware('throttle:game')->group(function () {
            Route::post('/', [GameSessionController::class, 'store'])->name('store');
            Route::post('{gameSession}/answers', [AnswerController::class, 'store'])->name('answers.store');
            Route::post('{gameSession}/advance', AdvanceGameController::class)->name('advance');
        });
    });
});
