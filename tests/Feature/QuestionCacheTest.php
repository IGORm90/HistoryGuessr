<?php

use App\Models\Question;
use App\Services\QuestionCache;
use Illuminate\Support\Facades\DB;

it('serves cached questions without hitting the database', function () {
    $question = Question::factory()->published()->create();
    $cache = app(QuestionCache::class);
    $cache->get($question->id);

    DB::enableQueryLog();
    $cached = $cache->get($question->id);

    expect(DB::getQueryLog())->toBe([])
        ->and($cached->latitude)->toBe($question->latitude)
        ->and($cached->answer_year)->toBe($question->answer_year);
});

it('invalidates the cache when a question changes', function () {
    $question = Question::factory()->published()->create(['location_name' => 'Old']);
    app(QuestionCache::class)->get($question->id);

    $question->update(['location_name' => 'New']);

    expect(app(QuestionCache::class)->get($question->id)->location_name)->toBe('New');
});
