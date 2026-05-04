<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('two_factor_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('artists', function (Blueprint $table): void {
            $table->id();
            $table->string('spotify_id')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('genre')->nullable();
            $table->unsignedBigInteger('followers')->default(0);
            $table->string('image_url')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedTinyInteger('popularity')->default(0);
            $table->timestamps();
        });

        Schema::create('albums', function (Blueprint $table): void {
            $table->id();
            $table->string('spotify_id')->nullable()->unique();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('cover_url')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->unsignedSmallInteger('total_tracks')->default(0);
            $table->timestamps();
        });

        Schema::create('songs', function (Blueprint $table): void {
            $table->id();
            $table->string('spotify_id')->nullable()->unique();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('duration_seconds');
            $table->string('preview_url')->nullable();
            $table->unsignedSmallInteger('track_number')->default(1);
            $table->boolean('explicit')->default(false);
            $table->unsignedTinyInteger('popularity')->default(0);
            $table->timestamps();
        });

        Schema::create('favorite_songs', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'song_id']);
        });

        Schema::create('followed_artists', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'artist_id']);
        });

        Schema::create('playlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover_url')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('playlist_song', function (Blueprint $table): void {
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->primary(['playlist_id', 'song_id']);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('resource_type')->index();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('playlist_song');
        Schema::dropIfExists('playlists');
        Schema::dropIfExists('followed_artists');
        Schema::dropIfExists('favorite_songs');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('artists');
        Schema::dropIfExists('two_factor_challenges');
        Schema::dropIfExists('api_tokens');
    }
};
