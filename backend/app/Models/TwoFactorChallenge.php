<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'user_id', 'code_hash', 'attempts_count', 'expires_at', 'consumed_at'])]
class TwoFactorChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'attempts_count' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(int $maxAttempts): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts_count < $maxAttempts;
    }

    public function registerFailedAttempt(int $maxAttempts): void
    {
        $attempts = $this->attempts_count + 1;

        $this->forceFill([
            'attempts_count' => $attempts,
            'consumed_at' => $attempts >= $maxAttempts ? now() : null,
        ])->save();
    }
}
