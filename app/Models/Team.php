<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'name',
        'captain_id',
        'invitation_code_hash',
        'invitation_code_encrypted',
        'status',
    ];

    protected $hidden = ['invitation_code_hash', 'invitation_code_encrypted'];

    protected function casts(): array
    {
        return [
            'invitation_code_encrypted' => 'encrypted',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_memberships')
            ->withPivot(['role', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->whereNull('team_memberships.left_at');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function attributedSolves(): HasMany
    {
        return $this->hasMany(VerifiedSolve::class, 'team_id_at_solve');
    }

    /**
     * Generate a unique 6-digit numeric invitation code.
     */
    public static function generateInvitationCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hash = hash('sha256', $code);

            if (! static::where('invitation_code_hash', $hash)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate a unique 6-digit invitation code.');
    }
}
