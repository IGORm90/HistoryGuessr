<?php

use App\Actions\Admin\DeleteQuestion;
use App\Actions\Admin\SyncQuestionQuizzes;
use App\Enums\PublicationStatus;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Models\Answer;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ImageProcessor;
use Filament\Actions\DeleteAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;
use Tests\Support\ImageFixtures;

beforeEach(function () {
    $this->disk = Storage::fake(config('filesystems.default'));
    $this->actingAs(User::factory()->create());
});

it('lists questions with formatted years', function () {
    $questions = Question::factory()->count(3)->create();
    Question::factory()->create(['answer' => 'Цезарь', 'answer_year' => -44]);

    Livewire::test(ListQuestions::class)
        ->assertOk()
        ->assertCanSeeTableRecords($questions)
        ->assertSee('44 г. до н. э.');
});

it('filters questions by status', function () {
    $published = Question::factory()->published()->create();
    $draft = Question::factory()->create();

    Livewire::test(ListQuestions::class)
        ->filterTable('status', PublicationStatus::Published->value)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('creates a draft with an uploaded image that gets processed', function () {
    $upload = UploadedFile::fake()->createWithContent('photo.jpg', ImageFixtures::jpegWithGps(2000, 1000));

    Livewire::test(CreateQuestion::class)
        ->fillForm([
            'answer' => 'Мартовские иды',
            'image_original_path' => $upload,
            'latitude' => 41.895466,
            'longitude' => 12.476768,
            'answer_year_absolute' => 44,
            'answer_year_bce' => true,
            'location_name' => 'Рим',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::sole();
    expect($question->status)->toBe(PublicationStatus::Draft)
        ->and($question->answer_year)->toBe(-44)
        ->and($question->latitude)->toBe(41.895466)
        ->and($question->image_original_path)->toMatch('#^originals/[0-9a-f-]{36}\.jpg$#')
        ->and($question->image_original_path)->not->toContain('photo')
        ->and($question->image_large_path)->not->toBeNull()
        ->and($this->disk->exists($question->image_medium_path))->toBeTrue();
});

it('allows saving an incomplete draft', function () {
    Livewire::test(CreateQuestion::class)
        ->fillForm(['answer' => 'Идея на потом'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Question::sole()->answer_year)->toBeNull();
});

it('rejects uploads that are not allowed image types', function () {
    $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    Livewire::test(CreateQuestion::class)
        ->fillForm(['answer' => 'Test', 'image_original_path' => $svg])
        ->call('create')
        ->assertHasFormErrors(['image_original_path']);
});

it('rejects coordinates out of range', function () {
    Livewire::test(CreateQuestion::class)
        ->fillForm(['answer' => 'Test', 'latitude' => 95, 'longitude' => 200])
        ->call('create')
        ->assertHasFormErrors(['latitude', 'longitude']);
});

it('hydrates BC years into number and era', function () {
    $question = Question::factory()->create(['answer_year' => -490]);

    Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->assertSchemaStateSet([
            'answer_year_absolute' => 490,
            'answer_year_bce' => true,
        ]);
});

it('requires attribution fields while a question is published', function () {
    $question = Question::factory()->published()->create();

    Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->fillForm(['image_license' => null, 'source_url' => null])
        ->call('save')
        ->assertHasFormErrors(['image_license', 'source_url']);
});

it('publishes from the edit page', function () {
    $question = Question::factory()->create();

    Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Опубликовано');

    expect($question->refresh()->isPublished())->toBeTrue();
});

it('explains why a question cannot be published', function () {
    $question = Question::factory()->create(['image_license' => null]);

    Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Нельзя опубликовать');

    expect($question->refresh()->isPublished())->toBeFalse();
});

it('bulk publishes and reports failures', function () {
    $ok = Question::factory()->create();
    $broken = Question::factory()->create(['image_license' => null]);

    Livewire::test(ListQuestions::class)
        ->callTableBulkAction('publishSelected', [$ok, $broken])
        ->assertNotified('Опубликовано: 1 из 2');

    expect($ok->refresh()->isPublished())->toBeTrue()
        ->and($broken->refresh()->isPublished())->toBeFalse();
});

it('bulk archives questions', function () {
    $questions = Question::factory()->published()->count(2)->create();

    Livewire::test(ListQuestions::class)->callTableBulkAction('archiveSelected', $questions);

    expect(Question::where('status', PublicationStatus::Archived)->count())->toBe(2);
});

it('bulk adds questions to a quiz', function () {
    $quiz = Quiz::factory()->create();
    $questions = Question::factory()->count(2)->create();

    Livewire::test(ListQuestions::class)
        ->callTableBulkAction('addToQuiz', $questions, data: ['quiz_id' => $quiz->id, 'weight' => 2]);

    expect($quiz->questions()->count())->toBe(2)
        ->and($quiz->questions()->first()->pivot->weight)->toBe(2);
});

/**
 * @return array<int, array{position: int, weight: int}>
 */
function quizPivots(Question $question): array
{
    return $question->quizzes()->get()
        ->mapWithKeys(fn (Quiz $quiz): array => [$quiz->id => [
            'position' => (int) $quiz->getRelationValue('pivot')->position,
            'weight' => (int) $quiz->getRelationValue('pivot')->weight,
        ]])
        ->all();
}

it('adds a new question to the end of the chosen quizzes', function () {
    $filled = Quiz::factory()->withQuestions(3)->create();
    $empty = Quiz::factory()->published()->create();
    $other = Quiz::factory()->create();

    Livewire::test(CreateQuestion::class)
        ->fillForm(['answer' => 'Мартовские иды', 'quizzes' => [$filled->id, $empty->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::query()->where('answer', 'Мартовские иды')->sole();

    expect(quizPivots($question))->toEqual([
        $filled->id => ['position' => 4, 'weight' => 1],
        $empty->id => ['position' => 1, 'weight' => 1],
    ])
        ->and($other->questions()->count())->toBe(0);
});

it('creates a question without quizzes', function () {
    Livewire::test(CreateQuestion::class)
        ->fillForm(['answer' => 'Без подборки'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Question::sole()->quizzes()->count())->toBe(0);
});

it('does not offer archived quizzes for a new question', function () {
    $archived = Quiz::factory()->create(['status' => PublicationStatus::Archived]);

    Livewire::test(CreateQuestion::class)
        ->fillForm(['answer' => 'В архив нельзя', 'quizzes' => [$archived->id]])
        ->call('create')
        ->assertHasFormErrors(['quizzes.0']);

    expect(Question::query()->count())->toBe(0);
});

it('syncs quizzes on edit, keeping order and weight of the kept ones', function () {
    $question = Question::factory()->create();
    $kept = Quiz::factory()->withQuestions(2)->create();
    $removed = Quiz::factory()->create();
    $added = Quiz::factory()->withQuestions(1)->create();
    $kept->questions()->attach($question->id, ['position' => 2, 'weight' => 5]);
    $removed->questions()->attach($question->id, ['position' => 1, 'weight' => 1]);

    $page = Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()]);

    expect(collect($page->get('data.quizzes'))->map(fn ($id): int => (int) $id)->sort()->values()->all())
        ->toBe(collect([$kept->id, $removed->id])->sort()->values()->all());

    $page->fillForm(['quizzes' => [$kept->id, $added->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(quizPivots($question))->toEqual([
        $kept->id => ['position' => 2, 'weight' => 5],
        $added->id => ['position' => 2, 'weight' => 1],
    ]);
});

it('keeps an archived quiz the question already belongs to', function () {
    $question = Question::factory()->create();
    $archived = Quiz::factory()->create(['status' => PublicationStatus::Archived]);
    $archived->questions()->attach($question->id, ['position' => 3, 'weight' => 2]);

    Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(quizPivots($question))->toEqual([$archived->id => ['position' => 3, 'weight' => 2]]);
});

it('syncs quizzes idempotently', function () {
    $question = Question::factory()->create();
    $quiz = Quiz::factory()->withQuestions(2)->create();
    $sync = app(SyncQuestionQuizzes::class);

    $sync->handle($question, [$quiz->id, (string) $quiz->id]);
    $sync->handle($question, [$quiz->id]);

    expect(quizPivots($question))->toEqual([$quiz->id => ['position' => 3, 'weight' => 1]]);
});

it('renders the preview with the reveal', function () {
    $question = Question::factory()->create(['location_name' => 'Порт-Саид', 'answer_year' => 1869]);

    Livewire::test(ListQuestions::class)
        ->mountTableAction('preview', $question)
        ->assertMountedActionModalSee(['Порт-Саид', '1869 г.']);
});

it('allows deleting only archived questions', function () {
    foreach ([Question::factory()->create(), Question::factory()->published()->create()] as $question) {
        Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    $archived = Question::factory()->archived()->create();
    Livewire::test(EditQuestion::class, ['record' => $archived->getRouteKey()])
        ->assertActionVisible(DeleteAction::class);
});

it('deletes an archived played question with its games and images', function () {
    $question = Question::factory()->archived()->create([
        'image_original_path' => 'originals/a.jpg',
        'image_large_path' => 'questions/a/large.webp',
        'image_medium_path' => 'questions/a/medium.webp',
    ]);
    foreach (['originals/a.jpg', 'questions/a/large.webp', 'questions/a/medium.webp'] as $path) {
        $this->disk->put($path, 'x');
    }
    $quiz = Quiz::factory()->create();
    $quiz->questions()->attach($question->id, ['position' => 1, 'weight' => 1]);

    $answered = Answer::factory()->create(['question_id' => $question->id])->gameSession;
    $shown = GameSession::factory()->create();
    $shown->questions()->attach($question->id, ['position' => 1]);
    $other = Answer::factory()->create();

    Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->assertActionVisible(DeleteAction::class)
        ->mountAction(DeleteAction::class)
        ->assertMountedActionModalSee('(2)')
        ->callMountedAction();

    expect(Question::find($question->id))->toBeNull()
        ->and(GameSession::find($answered->id))->toBeNull()
        ->and(GameSession::find($shown->id))->toBeNull()
        ->and($other->fresh())->not->toBeNull()
        ->and($quiz->fresh())->not->toBeNull()
        ->and($this->disk->exists('originals/a.jpg'))->toBeFalse()
        ->and($this->disk->exists('questions/a/large.webp'))->toBeFalse();
});

it('deletes archived questions from the table and skips the rest in bulk', function () {
    $row = Question::factory()->archived()->create();
    $archived = Question::factory()->archived()->create();
    $published = Question::factory()->published()->create();

    Livewire::test(ListQuestions::class)
        ->assertTableActionHidden(DeleteAction::class, $published)
        ->callTableAction(DeleteAction::class, $row)
        ->callTableBulkAction('deleteSelected', [$archived, $published]);

    expect(Question::pluck('id')->all())->toBe([$published->id]);
});

it('refuses to delete a question that is not archived', function () {
    app(DeleteQuestion::class)->handle(Question::factory()->published()->create());
})->throws(InvalidArgumentException::class);

it('keeps public ids out of the form', function () {
    $question = Question::factory()->create();

    Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->assertOk();

    expect(Str::isUuid($question->public_id))->toBeTrue();
});

it('keeps Livewire temporary uploads off S3 and allows the full image size', function () {
    expect(FileUploadConfiguration::isUsingS3())->toBeFalse()
        ->and(FileUploadConfiguration::rules())->toContain('max:'.(ImageProcessor::MAX_BYTES / 1024));
});
