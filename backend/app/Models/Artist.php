<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['spotify_id', 'name', 'slug', 'genre', 'followers', 'image_url', 'bio', 'popularity'])]
class Artist extends Model
{
    use HasFactory;

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function followedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'followed_artists')->withTimestamps();
    }
}
