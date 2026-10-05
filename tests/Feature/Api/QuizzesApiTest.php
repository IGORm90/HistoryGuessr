<?php

use App\Models\Question;
use App\Models\Quiz;

it('lists only published quizzes that have playable questions', function () {
    $playable = Quiz::factory()->published()->withQuestions(3)->create();
    Quiz::factory()->withQuestions(3)->create(); // draft
    $empty = Quiz::factory()->published()->create();
    $empty->questions()->attach(Question::factory()->create(), ['position' => 1]); // only a draft question

    $this->getJson('/api/v1/quizzes')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $playable->public_id)
        ->assertJsonStructure([['id', 'slug', 'title', 'description', 'cover_url', 'round_size']]);
});

it('never exposes internal ids', function () {
    $quiz = Quiz::factory()->published()->withQuestions(1)->create();

    $json = $this->getJson('/api/v1/quizzes')->json();

    expect($json[0])->not->toHaveKey('public_id')
        ->and($json[0]['id'])->toBe($quiz->public_id);
});
