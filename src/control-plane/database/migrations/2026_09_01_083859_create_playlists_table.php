<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_public')->default(true);
        });

        Schema::create('playlist_tracks', function (Blueprint $table) {
            $table->foreignUuid('playlist_id')
                ->constrained('playlists')
                ->onDelete('cascade');
            $table->foreignUuid('track_id')
                ->constrained('tracks')
                ->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->timestamp('added_at')->useCurrent();

            $table->primary(['playlist_id', 'track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_tracks');
        Schema::dropIfExists('playlists');
    }
};
