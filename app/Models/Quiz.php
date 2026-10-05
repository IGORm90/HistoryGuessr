<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\QuizFactory;
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
 * @property string $slug
 * @property string|null $description
 * @property string|null $cover_image_path
 * @property int $round_size
 * @property PublicationStatus $status
 * @property CarbonImmutable|null $published_at
 */
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_image_path',
        'round_size',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'status' => 'draft',
        'round_size' => 5,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_size' => 'integer',
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
     * Questions in editorial order (`position`); the game samples from them by `weight`.
     *
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_questions')
            ->withPivot(['position', 'weight'])
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<GameSession, $this>
     */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published;
    }
}
