<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Enums\QuestionDifficulty;
use App\Models\Concerns\HasPublicId;
use App\Observers\QuestionObserver;
use App\Support\HistoricalYear;
use Carbon\CarbonImmutable;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string|null $prompt
 * @property string|null $image_original_path
 * @property string|null $image_large_path
 * @property string|null $image_medium_path
 * @property int|null $image_width
 * @property int|null $image_height
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $answer_year
 * @property string|null $location_name
 * @property string|null $reveal_text
 * @property string|null $source_url
 * @property string|null $image_author
 * @property string|null $image_license
 * @property string|null $image_attribution
 * @property QuestionDifficulty $difficulty
 * @property PublicationStatus $status
 * @property CarbonImmutable|null $published_at
 */
#[ObservedBy(QuestionObserver::class)]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'title',
        'prompt',
        'image_original_path',
        'image_large_path',
        'image_medium_path',
        'image_width',
        'image_height',
        'latitude',
        'longitude',
        'answer_year',
        'location_name',
        'reveal_text',
        'source_url',
        'image_author',
        'image_license',
        'image_attribution',
        'difficulty',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'status' => 'draft',
        'difficulty' => 2,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'answer_year' => 'integer',
            'image_width' => 'integer',
            'image_height' => 'integer',
            'difficulty' => QuestionDifficulty::class,
            'status' => PublicationStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PublicationStatus::Published);
    }

    /**
     * @return BelongsToMany<Quiz, $this>
     */
    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_questions')->withPivot(['position', 'weight']);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function historicalYear(): ?HistoricalYear
    {
        return $this->answer_year === null ? null : new HistoricalYear($this->answer_year);
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published;
    }
}
