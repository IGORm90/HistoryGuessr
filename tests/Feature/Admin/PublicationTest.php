<?php

use App\Actions\Admin\AttachQuestionsToQuiz;
use App\Actions\Admin\PublishQuestion;
use App\Actions\Admin\PublishQuiz;
use App\Enums\PublicationStatus;
use App\Exceptions\PublicationException;
use App\Models\Question;
use App\Models\Quiz;

it('publishes a complete question', function () {
    $question = Question::factory()->create();

    app(PublishQuestion::class)->handle($question);

    expect($question->refresh()->status)->toBe(PublicationStatus::Published)
        ->and($question->published_at)->not->toBeNull();
});

it('lists every problem that blocks publication', function () {
    $question = Question::factory()->incomplete()->create(['image_original_path' => 'originals/x.jpg']);

    try {
        app(PublishQuestion::class)->handle($question);
        $this->fail('Expected PublicationException');
    } catch (PublicationException $e) {
        expect($e->problems[0])->toBe('изображение ещё обрабатывается')
            ->and($e->problems[1])->toContain('лицензия изображения')->toContain('ссылка на источник')->toContain('год');
    }

    expect($question->refresh()->status)->toBe(PublicationStatus::Draft);
});

it('refuses to publish a quiz with fewer published questions than the round size', function () {
    $quiz = Quiz::factory()->create(['round_size' => 5]);
    app(AttachQuestionsToQuiz::class)->handle($quiz, Question::factory()->published()->count(4)->create()->pluck('id'));
    app(AttachQuestionsToQuiz::class)->handle($quiz, Question::factory()->count(3)->create()->pluck('id'));

    app(PublishQuiz::class)->handle($quiz);
})->throws(PublicationException::class, 'опубликованных вопросов 4, а в раунде 5');

it('publishes a quiz with enough published questions', function () {
    $quiz = Quiz::factory()->withQuestions(5)->create(['round_size' => 5]);

    app(PublishQuiz::class)->handle($quiz);

    expect($quiz->refresh()->isPublished())->toBeTrue();
});

it('appends questions to the end of a quiz and skips duplicates', function () {
    $quiz = Quiz::factory()->withQuestions(2)->create();
    $existing = $quiz->questions->first();
    $new = Question::factory()->count(2)->create();

    $added = app(AttachQuestionsToQuiz::class)->handle($quiz, [$existing->id, ...$new->pluck('id')], weight: 3);

    expect($added)->toBe(2)
        ->and($quiz->questions()->get()->map(fn ($q) => [$q->id, $q->pivot->position, $q->pivot->weight])->all())
        ->toBe([
            [$quiz->questions[0]->id, 1, 1],
            [$quiz->questions[1]->id, 2, 1],
            [$new[0]->id, 3, 3],
            [$new[1]->id, 4, 3],
        ]);
});
