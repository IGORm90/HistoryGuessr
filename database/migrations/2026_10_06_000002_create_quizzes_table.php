<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->unsignedSmallInteger('round_size')->default(5);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_round_size_range CHECK (round_size BETWEEN 1 AND 50)');
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_status_valid CHECK (status IN ('draft', 'published', 'archived'))");
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_published_at CHECK (status <> 'published' OR published_at IS NOT NULL)");

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedSmallInteger('weight')->default(1);

            $table->primary(['quiz_id', 'question_id']);
            $table->index(['quiz_id', 'position']);
            $table->index('question_id');
        });

        DB::statement('ALTER TABLE quiz_questions ADD CONSTRAINT quiz_questions_weight_positive CHECK (weight >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
