<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'category',
        'difficulty',
        'points',
        'target_type',
        'target_url',
        'is_active',
        'flag',
        'hints',
        'published_at',
        'attachment_path',
        'attachment_filename',
    ];

    /**
     * The canonical flag is a secret. It is hidden from every array and
     * JSON representation of the model so it can never leak through an
     * API response or serialized frontend payload.
     */
    protected $hidden = [
        'flag',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
            'flag' => 'encrypted',
            'hints' => 'array',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChallengeSubmission::class);
    }

    public function verifiedSolves(): HasMany
    {
        return $this->hasMany(VerifiedSolve::class);
    }

    /**
     * A challenge is visible to participants only once it has been
     * published (a published_at timestamp in the past).
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * A challenge is live when it is both active and published.
     */
    public function isLive(): bool
    {
        return (bool) $this->is_active && $this->isPublished();
    }

    /**
     * Resolve the URL a participant opens to work on the challenge.
     *
     * Internal challenges are served by this application; external
     * challenges point at the configured https URL. No server-side
     * request is ever made to the target from this application.
     */
    public function resolveTargetUrl(): string
    {
        if ($this->target_type === 'external' && filled($this->target_url)) {
            return $this->target_url;
        }

        return route('challenges.target', ['challenge' => $this]);
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function downloadFilename(): string
    {
        return $this->attachment_filename ?: basename((string) $this->attachment_path);
    }

    /**
     * Hints are revealed progressively. Normalise to a list of strings.
     *
     * @return array<int, string>
     */
    public function hintList(): array
    {
        $hints = $this->hints;

        if (! is_array($hints)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $hints), fn ($hint) => $hint !== ''));
    }
}
