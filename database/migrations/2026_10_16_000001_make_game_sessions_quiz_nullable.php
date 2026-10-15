<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A game without a quiz draws random published questions ("Начать игру" on the home page).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('quiz_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('quiz_id')->nullable(false)->change();
        });
    }
};
