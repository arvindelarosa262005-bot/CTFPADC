<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifiedSolve extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'challenge_id',
        'user_id',
        'team_id_at_solve',
        'points_awarded',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'points_awarded' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teamAtSolve(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id_at_solve');
    }
}
