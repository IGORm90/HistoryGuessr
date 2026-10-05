<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scored guess. Scores are computed once at answer time and never recalculated.
 *
 * @property int $id
 * @property int $game_session_id
 * @property int $question_id
 * @property string $idempotency_key
 * @property float $guessed_latitude
 * @property float $guessed_longitude
 * @property int $guessed_year
 * @property int $distance_meters
 * @property int $year_difference
 * @property int $geo_score
 * @property int $year_score
 * @property int $total_score
 * @property int|null $duration_ms
 * @property CarbonImmutable $answered_at
 */
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'game_session_id',
        'question_id',
        'idempotency_key',
        'guessed_latitude',
        'guessed_longitude',
        'guessed_year',
        'distance_meters',
        'year_difference',
        'geo_score',
        'year_score',
        'total_score',
        'duration_ms',
        'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guessed_latitude' => 'float',
            'guessed_longitude' => 'float',
            'guessed_year' => 'integer',
            'distance_meters' => 'integer',
            'year_difference' => 'integer',
            'geo_score' => 'integer',
            'year_score' => 'integer',
            'total_score' => 'integer',
            'duration_ms' => 'integer',
            'answered_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
