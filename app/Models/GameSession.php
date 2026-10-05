<?php

namespace App\Models;

use App\Enums\GameSessionStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $quiz_id
 * @property string $anonymous_id
 * @property GameSessionStatus $status
 * @property int $current_position
 * @property int $question_count
 * @property int $total_score
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 */
class GameSession extends Model
{
    /** @use HasFactory<GameSessionFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'quiz_id',
        'anonymous_id',
        'status',
        'current_position',
        'question_count',
        'total_score',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'in_progress',
        'current_position' => 1,
        'total_score' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameSessionStatus::class,
            'current_position' => 'integer',
            'question_count' => 'integer',
            'total_score' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * The fixed question order of this game.
     *
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'game_session_questions')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === GameSessionStatus::InProgress;
    }

    public function isLastPosition(): bool
    {
        return $this->current_position >= $this->question_count;
    }
}
