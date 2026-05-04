<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IMPORTANTE:
        // Las tablas "favorites" y "followed_artists" ya se crean en
        // 2026_04_27_000002_create_musichub_tables.php
        // Aquí SOLO definimos la tabla nueva "playlist_tracks".

        Schema::create('playlist_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->string('spotify_track_id')->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_tracks');
    }
};
