<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'contestant',
        'is_active' => true,
    ];

    protected $fillable = [
        'username',
        'email',
        'google_id',
        'avatar',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPrimaryAdmin(): bool
    {
        $primaryEmail = config('auth.primary_admin.email');
        $primaryUsername = config('auth.primary_admin.username');

        return $this->isAdmin() && (
            strtolower($this->username) === 'admin'
            || ($primaryEmail !== '' && strtolower($this->email) === $primaryEmail)
            || ($primaryUsername !== '' && strtolower($this->username) === $primaryUsername)
        );
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_memberships')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function activeTeams(): BelongsToMany
    {
        return $this->teams()->whereNull('team_memberships.left_at');
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function verifiedSolves(): HasMany
    {
        return $this->hasMany(VerifiedSolve::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
