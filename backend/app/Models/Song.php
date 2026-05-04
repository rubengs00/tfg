<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['spotify_id', 'album_id', 'title', 'duration_seconds', 'preview_url', 'track_number', 'explicit', 'popularity'])]
class Song extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'explicit' => 'boolean',
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)->withPivot('position')->withTimestamps();
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_songs')->withTimestamps();
    }
}
