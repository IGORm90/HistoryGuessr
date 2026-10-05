<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('title');
            $table->text('prompt')->nullable();

            $table->string('image_original_path')->nullable();
            $table->string('image_large_path')->nullable();
            $table->string('image_medium_path')->nullable();
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();

            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->integer('answer_year')->nullable();
            $table->string('location_name')->nullable();
            $table->text('reveal_text')->nullable();

            $table->string('source_url', 2048)->nullable();
            $table->string('image_author')->nullable();
            $table->string('image_license')->nullable();
            $table->text('image_attribution')->nullable();

            $table->unsignedSmallInteger('difficulty')->default(2);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_answer_year_not_zero CHECK (answer_year <> 0)');
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_latitude_range CHECK (latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_longitude_range CHECK (longitude BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_difficulty_range CHECK (difficulty BETWEEN 1 AND 3)');
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_status_valid CHECK (status IN ('draft', 'published', 'archived'))");
        // Drafts may be incomplete; a published question must be playable and properly attributed.
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_published_complete CHECK (
            status <> 'published' OR (
                prompt IS NOT NULL AND image_large_path IS NOT NULL AND image_medium_path IS NOT NULL
                AND latitude IS NOT NULL AND longitude IS NOT NULL AND answer_year IS NOT NULL
                AND location_name IS NOT NULL AND reveal_text IS NOT NULL
                AND source_url IS NOT NULL AND image_license IS NOT NULL AND published_at IS NOT NULL
            )
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
