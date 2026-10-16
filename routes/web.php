<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

// Vue SPA entry point: every path not claimed by the API, admin panel or framework routes.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|hstradmn|sanctum|livewire|filament|storage|health).*$')
    ->name('spa');
