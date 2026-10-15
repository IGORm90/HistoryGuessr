<?php

use App\Filament\Resources\Quizzes\Pages\CreateQuiz;
use App\Filament\Resources\Quizzes\Pages\EditQuiz;
use App\Filament\Resources\Quizzes\Pages\ListQuizzes;
use App\Filament\Resources\Quizzes\RelationManagers\QuestionsRelationManager;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\ImageFixtures;

beforeEach(function () {
    $this->disk = Storage::fake(config('filesystems.default'));
    $this->actingAs(User::factory()->create());
});

it('lists quizzes with published question counts', function () {
    $quiz = Quiz::factory()->withQuestions(3)->create();

    Livewire::test(ListQuizzes::class)
        ->assertCanSeeTableRecords([$quiz])
        ->assertSee('3 / 3');
});

it('creates a quiz with a processed cover', function () {
    Livewire::test(CreateQuiz::class)
        ->fillForm([
            'title' => 'Античность',
            'slug' => 'antiquity',
            'round_size' => 5,
            'cover_image_path' => UploadedFile::fake()->createWithContent('cover.jpg', ImageFixtures::jpegWithGps(1800, 1200)),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quiz = Quiz::sole();
    $cover = new Imagick;
    $cover->readImageBlob($this->disk->get($quiz->cover_image_path));

    expect($quiz->slug)->toBe('antiquity')
        ->and($quiz->cover_image_path)->toMatch('#^quizzes/[0-9a-f-]{36}\.webp$#')
        ->and([$cover->getImageWidth(), $cover->getImageHeight()])->toBe([1200, 800])
        ->and(str_contains($this->disk->get($quiz->cover_image_path), 'Exif'))->toBeFalse();
});

it('refuses a cover that was not cropped to 3:2', function () {
    Livewire::test(CreateQuiz::class)
        ->fillForm([
            'title' => 'Античность',
            'slug' => 'antiquity',
            'round_size' => 5,
            'cover_image_path' => UploadedFile::fake()->createWithContent('cover.jpg', ImageFixtures::jpegWithGps(1000, 1000)),
        ])
        ->call('create')
        ->assertHasFormErrors(['cover_image_path']);

    expect(Quiz::count())->toBe(0);
});

it('requires a unique slug', function () {
    Quiz::factory()->create(['slug' => 'taken']);

    Livewire::test(CreateQuiz::class)
        ->fillForm(['title' => 'X', 'slug' => 'taken', 'round_size' => 5])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('attaches questions through the relation manager', function () {
    $quiz = Quiz::factory()->create();
    $questions = Question::factory()->count(2)->create();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $quiz, 'pageClass' => EditQuiz::class])
        ->callTableAction('attachQuestions', data: ['question_ids' => $questions->pluck('id')->all(), 'weight' => 1])
        ->assertCanSeeTableRecords($questions, inOrder: true);

    expect($quiz->questions()->pluck('quiz_questions.position')->all())->toBe([1, 2]);
});

it('reorders questions by drag and drop', function () {
    $quiz = Quiz::factory()->withQuestions(3)->create();
    [$a, $b, $c] = $quiz->questions->all();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $quiz, 'pageClass' => EditQuiz::class])
        ->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

    expect($quiz->questions()->pluck('questions.id')->all())->toBe([$c->id, $a->id, $b->id]);
});

it('edits a question weight', function () {
    $quiz = Quiz::factory()->withQuestions(1)->create();
    $question = $quiz->questions->first();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $quiz, 'pageClass' => EditQuiz::class])
        ->callTableAction('editWeight', $question, data: ['weight' => 4]);

    expect($quiz->questions()->first()->pivot->weight)->toBe(4);
});

it('refuses to publish a quiz without enough published questions', function () {
    $quiz = Quiz::factory()->withQuestions(2)->create(['round_size' => 5]);

    Livewire::test(EditQuiz::class, ['record' => $quiz->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Нельзя опубликовать');

    expect($quiz->refresh()->isPublished())->toBeFalse();
});

it('does not allow deleting a quiz that has been played', function () {
    $quiz = GameSession::factory()->create()->quiz;

    Livewire::test(EditQuiz::class, ['record' => $quiz->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);
});
