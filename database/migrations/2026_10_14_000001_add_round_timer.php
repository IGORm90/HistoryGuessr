<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side round timer: every shown question gets a deadline. A timed-out answer either carries the
 * guess picked when time ran out, or no guess and zero points (the player was away).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // Snapshot of config('game.round.time_limit') at game start.
            $table->unsignedSmallInteger('round_time_limit_seconds')->default(90);
        });

        DB::statement('ALTER TABLE game_sessions ADD CONSTRAINT game_sessions_round_time_limit_positive CHECK (round_time_limit_seconds > 0)');

        Schema::table('game_session_questions', function (Blueprint $table) {
            // Null until the question becomes current.
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('deadline_at', 3)->nullable();
        });

        DB::statement('ALTER TABLE game_session_questions ADD CONSTRAINT game_session_questions_round_consistent CHECK ((started_at IS NULL) = (deadline_at IS NULL) AND (deadline_at IS NULL OR deadline_at > started_at))');

        // Games in progress get a fresh round for their current question.
        DB::statement(<<<'SQL'
            UPDATE game_session_questions AS gsq
            SET started_at = now(), deadline_at = now() + make_interval(secs => gs.round_time_limit_seconds)
            FROM game_sessions AS gs
            WHERE gs.id = gsq.game_session_id
              AND gs.status = 'in_progress'
              AND gsq.position = gs.current_position
            SQL);

        Schema::table('answers', function (Blueprint $table) {
            $table->boolean('timed_out')->default(false);
            $table->decimal('guessed_latitude', 9, 6)->nullable()->change();
            $table->decimal('guessed_longitude', 9, 6)->nullable()->change();
            $table->integer('guessed_year')->nullable()->change();
            $table->unsignedInteger('distance_meters')->nullable()->change();
            $table->unsignedInteger('year_difference')->nullable()->change();
        });

        // A guess is all or nothing. Without one the answer can only be a timeout worth zero;
        // a timeout may still carry the guess the player had picked when time ran out.
        DB::statement(<<<'SQL'
            ALTER TABLE answers ADD CONSTRAINT answers_guess_consistent CHECK (
                (guessed_latitude IS NULL) = (guessed_longitude IS NULL)
                AND (guessed_latitude IS NULL) = (guessed_year IS NULL)
                AND (guessed_latitude IS NULL) = (distance_meters IS NULL)
                AND (guessed_latitude IS NULL) = (year_difference IS NULL)
                AND (guessed_latitude IS NOT NULL OR (timed_out AND geo_score = 0 AND year_score = 0))
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE answers DROP CONSTRAINT answers_guess_consistent');
        DB::table('answers')->whereNull('guessed_latitude')->delete();

        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('timed_out');
            $table->decimal('guessed_latitude', 9, 6)->nullable(false)->change();
            $table->decimal('guessed_longitude', 9, 6)->nullable(false)->change();
            $table->integer('guessed_year')->nullable(false)->change();
            $table->unsignedInteger('distance_meters')->nullable(false)->change();
            $table->unsignedInteger('year_difference')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE game_session_questions DROP CONSTRAINT game_session_questions_round_consistent');

        Schema::table('game_session_questions', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'deadline_at']);
        });

        DB::statement('ALTER TABLE game_sessions DROP CONSTRAINT game_sessions_round_time_limit_positive');

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn('round_time_limit_seconds');
        });
    }
};
