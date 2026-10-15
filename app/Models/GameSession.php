<?php

namespace App\Models;

use App\DTO\Game\CurrentRound;
use App\Enums\GameSessionStatus;
use App\Models\Concerns\HasPublicId;
use App\Support\RoundClock;
use Carbon\CarbonImmutable;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $quiz_id
 * @property string $anonymous_id
 * @property GameSessionStatus $status
 * @property int $current_position
 * @property int $question_count
 * @property int $total_score
 * @property int $round_time_limit_seconds
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
        'round_time_limit_seconds',
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
            'round_time_limit_seconds' => 'integer',
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

    /**
     * The question at the current position with its round times; null if the order row is missing.
     */
    public function currentRound(): ?CurrentRound
    {
        $row = DB::table('game_session_questions')
            ->join('questions', 'questions.id', '=', 'game_session_questions.question_id')
            ->where('game_session_questions.game_session_id', $this->id)
            ->where('game_session_questions.position', $this->current_position)
            ->first(['questions.id', 'questions.public_id', 'game_session_questions.started_at', 'game_session_questions.deadline_at']);

        if ($row === null) {
            return null;
        }

        return new CurrentRound(
            (int) $row->id,
            (string) $row->public_id,
            RoundClock::parse($row->started_at),
            RoundClock::parse($row->deadline_at),
        );
    }

    /**
     * Starts the timer of the question at the current position (it has just been shown).
     */
    public function startCurrentRound(): void
    {
        $startedAt = now()->toImmutable();

        DB::table('game_session_questions')
            ->where('game_session_id', $this->id)
            ->where('position', $this->current_position)
            ->update([
                'started_at' => RoundClock::toDatabase($startedAt),
                'deadline_at' => RoundClock::toDatabase(RoundClock::deadline($startedAt, $this->round_time_limit_seconds)),
            ]);
    }
}
