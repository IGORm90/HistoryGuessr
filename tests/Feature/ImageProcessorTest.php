<?php

use App\Exceptions\InvalidImageException;
use App\Jobs\ProcessQuestionImage;
use App\Models\Question;
use App\Services\ImageProcessor;
use App\Services\QuestionCache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;

beforeEach(function () {
    $this->disk = Storage::fake(config('filesystems.default'));
});

it('builds a fixture that really carries GPS EXIF', function () {
    $path = tempnam(sys_get_temp_dir(), 'gps').'.jpg';
    file_put_contents($path, ImageFixtures::jpegWithGps(64, 64));

    expect(exif_read_data($path))->toHaveKey('GPSLatitude');
});

it('creates large and medium WebP variants without EXIF', function () {
    $this->disk->put('originals/photo.jpg', ImageFixtures::jpegWithGps(2400, 1600));

    $result = app(ImageProcessor::class)->processQuestionImage($this->disk, 'originals/photo.jpg');

    expect($result->width)->toBe(1600)
        ->and($result->height)->toBe(1067)
        ->and($result->largePath)->toStartWith('questions/')->toEndWith('/large.webp')
        ->and($result->mediumPath)->toEndWith('/medium.webp');

    foreach ([$result->largePath => 1600, $result->mediumPath => 960] as $path => $edge) {
        $binary = $this->disk->get($path);
        $image = new Imagick;
        $image->readImageBlob($binary);

        expect($image->getImageFormat())->toBe('WEBP')
            ->and(max($image->getImageWidth(), $image->getImageHeight()))->toBe($edge)
            ->and($image->getImageProperties('exif:*'))->toBe([])
            ->and(str_contains($binary, 'Exif'))->toBeFalse();
    }
});

it('does not upscale small images', function () {
    $image = new Imagick;
    $image->newImage(800, 600, new ImagickPixel('gray'));
    $image->setImageFormat('png');
    $this->disk->put('originals/small.png', $image->getImageBlob());

    $result = app(ImageProcessor::class)->processQuestionImage($this->disk, 'originals/small.png');

    expect([$result->width, $result->height])->toBe([800, 600]);
});

it('rejects files that are not real images', function (string $contents) {
    $this->disk->put('originals/fake.jpg', $contents);

    app(ImageProcessor::class)->processQuestionImage($this->disk, 'originals/fake.jpg');
})->throws(InvalidImageException::class)->with([
    'php disguised as jpg' => '<?php echo "pwned"; ?>',
    'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
]);

it('processes the question image when an original is set', function () {
    $this->disk->put('originals/new.jpg', ImageFixtures::jpegWithGps(1200, 800));
    $question = Question::factory()->incomplete()->create();

    $question->update(['image_original_path' => 'originals/new.jpg']);

    $question->refresh();
    expect($question->image_large_path)->not->toBeNull()
        ->and($this->disk->exists($question->image_large_path))->toBeTrue()
        ->and($this->disk->exists($question->image_medium_path))->toBeTrue()
        ->and([$question->image_width, $question->image_height])->toBe([1200, 800]);
});

it('replaces previous variants and deletes them', function () {
    $this->disk->put('originals/a.jpg', ImageFixtures::jpegWithGps(1000, 800));
    $this->disk->put('originals/b.jpg', ImageFixtures::jpegWithGps(1000, 800));
    $question = Question::factory()->incomplete()->create(['image_original_path' => 'originals/a.jpg']);
    $firstLarge = $question->refresh()->image_large_path;

    $question->update(['image_original_path' => 'originals/b.jpg']);

    expect($question->refresh()->image_large_path)->not->toBe($firstLarge)
        ->and($this->disk->exists($firstLarge))->toBeFalse();
});

it('ignores stale jobs whose original was replaced', function () {
    $question = Question::factory()->incomplete()->create();

    (new ProcessQuestionImage($question->id, 'originals/old.jpg'))->handle(app(ImageProcessor::class), app(QuestionCache::class));

    expect($question->refresh()->image_large_path)->toBeNull();
});
