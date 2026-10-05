<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Enums\QuestionDifficulty;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;

/**
 * Development content: real events with placeholder images (no real photos are committed).
 */
class QuestionSeeder extends Seeder
{
    /**
     * @var list<array{title: string, prompt: string, lat: float, lng: float, year: int, location: string, reveal: string, difficulty: QuestionDifficulty}>
     */
    private const array EVENTS = [
        ['title' => 'Падение Берлинской стены', 'prompt' => 'Толпа на бетонной стене празднует открытие границы.', 'lat' => 52.516275, 'lng' => 13.377704, 'year' => 1989, 'location' => 'Берлин, Бранденбургские ворота', 'reveal' => 'В ночь на 10 ноября 1989 года жители Восточного и Западного Берлина поднялись на стену у Бранденбургских ворот.', 'difficulty' => QuestionDifficulty::Easy],
        ['title' => 'Первый полёт братьев Райт', 'prompt' => 'Хрупкий биплан отрывается от песчаной дюны.', 'lat' => 36.014167, 'lng' => -75.668056, 'year' => 1903, 'location' => 'Китти-Хок, Северная Каролина', 'reveal' => '17 декабря 1903 года Flyer братьев Райт продержался в воздухе 12 секунд — первый управляемый полёт самолёта.', 'difficulty' => QuestionDifficulty::Medium],
        ['title' => 'Старт «Аполлона-11»', 'prompt' => 'Огромная ракета поднимается со стартового стола на берегу океана.', 'lat' => 28.608389, 'lng' => -80.604333, 'year' => 1969, 'location' => 'Мыс Канаверал, стартовый комплекс 39A', 'reveal' => '16 июля 1969 года Saturn V унёс к Луне Армстронга, Олдрина и Коллинза.', 'difficulty' => QuestionDifficulty::Easy],
        ['title' => 'Открытие Эйфелевой башни', 'prompt' => 'Ажурная металлическая башня возвышается над всемирной выставкой.', 'lat' => 48.858370, 'lng' => 2.294481, 'year' => 1889, 'location' => 'Париж, Марсово поле', 'reveal' => 'Башня Гюстава Эйфеля стала входной аркой Всемирной выставки 1889 года.', 'difficulty' => QuestionDifficulty::Easy],
        ['title' => 'Убийство Юлия Цезаря', 'prompt' => 'Сенаторы окружают диктатора в курии.', 'lat' => 41.895466, 'lng' => 12.476768, 'year' => -44, 'location' => 'Рим, курия Помпея', 'reveal' => 'В мартовские иды 44 года до н. э. заговорщики во главе с Брутом и Кассием убили Цезаря.', 'difficulty' => QuestionDifficulty::Medium],
        ['title' => 'Марафонская битва', 'prompt' => 'Греческие гоплиты бегом атакуют персидское войско на равнине у моря.', 'lat' => 38.118333, 'lng' => 23.978333, 'year' => -490, 'location' => 'Марафонская равнина, Аттика', 'reveal' => 'Афиняне и платейцы под командованием Мильтиада разбили персов Дария I.', 'difficulty' => QuestionDifficulty::Hard],
        ['title' => 'Коронация Карла Великого', 'prompt' => 'Папа возлагает корону на голову франкского короля.', 'lat' => 41.902168, 'lng' => 12.453937, 'year' => 800, 'location' => 'Рим, старая базилика Святого Петра', 'reveal' => 'На Рождество 800 года папа Лев III короновал Карла императором.', 'difficulty' => QuestionDifficulty::Hard],
        ['title' => 'Отплытие «Титаника»', 'prompt' => 'Огромный четырёхтрубный лайнер отходит от причала.', 'lat' => 50.898000, 'lng' => -1.404000, 'year' => 1912, 'location' => 'Саутгемптон, причал 44', 'reveal' => '10 апреля 1912 года «Титаник» вышел в свой первый и последний рейс.', 'difficulty' => QuestionDifficulty::Medium],
        ['title' => 'Открытие Московского метро', 'prompt' => 'Пассажиры спускаются на мраморную платформу первой линии.', 'lat' => 55.789304, 'lng' => 37.679895, 'year' => 1935, 'location' => 'Москва, станция «Сокольники»', 'reveal' => '15 мая 1935 года открылся первый участок метро от «Сокольников» до «Парка культуры».', 'difficulty' => QuestionDifficulty::Medium],
        ['title' => 'Битва при Гастингсе', 'prompt' => 'Нормандская конница атакует стену щитов на холме.', 'lat' => 50.911944, 'lng' => 0.487500, 'year' => 1066, 'location' => 'Баттл, Восточный Суссекс', 'reveal' => 'Вильгельм Завоеватель разбил короля Гарольда II и стал королём Англии.', 'difficulty' => QuestionDifficulty::Hard],
        ['title' => 'Высадка в Нормандии', 'prompt' => 'Десантные катера подходят к песчаному пляжу под огнём.', 'lat' => 49.369400, 'lng' => -0.871400, 'year' => 1944, 'location' => 'Нормандия, пляж «Омаха»', 'reveal' => '6 июня 1944 года союзники начали крупнейшую морскую десантную операцию в истории.', 'difficulty' => QuestionDifficulty::Easy],
        ['title' => 'Открытие Суэцкого канала', 'prompt' => 'Вереница пароходов входит в узкий канал посреди пустыни.', 'lat' => 31.265300, 'lng' => 32.301900, 'year' => 1869, 'location' => 'Порт-Саид, Египет', 'reveal' => '17 ноября 1869 года канал соединил Средиземное и Красное моря.', 'difficulty' => QuestionDifficulty::Medium],
    ];

    public function run(): void
    {
        $questions = collect(self::EVENTS)->map(function (array $event, int $index): Question {
            $key = (string) Str::uuid();
            [$largePath, $mediumPath] = $this->storePlaceholderImages($key, $index);

            return Question::create([
                'title' => $event['title'],
                'prompt' => $event['prompt'],
                'image_large_path' => $largePath,
                'image_medium_path' => $mediumPath,
                'image_width' => 1600,
                'image_height' => 1067,
                'latitude' => $event['lat'],
                'longitude' => $event['lng'],
                'answer_year' => $event['year'],
                'location_name' => $event['location'],
                'reveal_text' => $event['reveal'],
                'source_url' => 'https://example.com/placeholder',
                'image_author' => null,
                'image_license' => 'Placeholder (dev only)',
                'difficulty' => $event['difficulty'],
                'status' => PublicationStatus::Published,
                'published_at' => now(),
            ]);
        });

        $quiz = Quiz::create([
            'title' => 'Поворотные моменты истории',
            'slug' => 'turning-points',
            'description' => 'От Марафона до падения Берлинской стены.',
            'round_size' => 5,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $quiz->questions()->attach(
            $questions->mapWithKeys(fn (Question $question, int $index) => [
                $question->id => ['position' => $index + 1, 'weight' => 1],
            ])->all(),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function storePlaceholderImages(string $key, int $index): array
    {
        $paths = ["questions/{$key}/large.webp", "questions/{$key}/medium.webp"];

        if (! extension_loaded('imagick')) {
            return $paths;
        }

        foreach ([[$paths[0], 1600, 1067], [$paths[1], 960, 640]] as [$path, $width, $height]) {
            $hue = ($index * 37) % 360;
            $image = new Imagick;
            $image->newPseudoImage($width, $height, "gradient:hsl({$hue},35%,70%)-hsl({$hue},40%,25%)");
            $image->sepiaToneImage(80);
            $image->setImageBackgroundColor(new ImagickPixel('white'));
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(70);

            Storage::put($path, $image->getImageBlob());
            $image->clear();
        }

        return $paths;
    }
}
