<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('quiz_id')->constrained()->restrictOnDelete();
            $table->uuid('anonymous_id')->index();
            $table->string('status', 20)->default('in_progress');
            // 1-based position of the question currently shown to the player.
            $table->unsignedSmallInteger('current_position')->default(1);
            $table->unsignedSmallInteger('question_count');
            $table->unsignedInteger('total_score')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE game_sessions ADD CONSTRAINT game_sessions_status_valid CHECK (status IN ('in_progress', 'completed', 'abandoned'))");
        DB::statement('ALTER TABLE game_sessions ADD CONSTRAINT game_sessions_position_range CHECK (current_position BETWEEN 1 AND question_count)');

        Schema::create('game_session_questions', function (Blueprint $table) {
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');

            $table->primary(['game_session_id', 'position']);
            $table->unique(['game_session_id', 'question_id']);
            $table->index('question_id');
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->decimal('guessed_latitude', 9, 6);
            $table->decimal('guessed_longitude', 9, 6);
            $table->integer('guessed_year');
            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('year_difference');
            $table->unsignedSmallInteger('geo_score');
            $table->unsignedSmallInteger('year_score');
            $table->unsignedSmallInteger('total_score');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('answered_at');

            $table->unique(['game_session_id', 'question_id']);
            $table->unique(['game_session_id', 'idempotency_key']);
            $table->index(['question_id', 'answered_at']);
        });

        DB::statement('ALTER TABLE answers ADD CONSTRAINT answers_guessed_year_not_zero CHECK (guessed_year <> 0)');
        DB::statement('ALTER TABLE answers ADD CONSTRAINT answers_latitude_range CHECK (guessed_latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE answers ADD CONSTRAINT answers_longitude_range CHECK (guessed_longitude BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE answers ADD CONSTRAINT answers_total_is_sum CHECK (total_score = geo_score + year_score)');
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
        Schema::dropIfExists('game_session_questions');
        Schema::dropIfExists('game_sessions');
    }
};
