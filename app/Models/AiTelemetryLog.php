<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTelemetryLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_telemetry_logs';

    protected $fillable = [
        'user_id',
        'challenge_id',
        'event_type',
        'severity',
        'details',
        'submitted_content',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
