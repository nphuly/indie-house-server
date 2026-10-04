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
        Schema::create('tracks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();

            $table->foreignId('artist_id')
                ->constrained('artists')
                ->onDelete('restrict');

            $table->string('title');
            $table->string('slug');
            $table->integer('duration_seconds')->default(0);

            $table->string('genre')->nullable();
            $table->text('lyrics')->nullable();

            // Storage paths (MinIO)
            $table->string('raw_file_key')->nullable();

            // Status & Worker processing logs
            $table->string('status', 50)->default('pending');
            $table->text('last_error_message')->nullable();

            $table->boolean('is_published')->default(false);
            $table->bigInteger('play_count')->default(0);

            // Indexes optimized queries for Go API Streaming & Lookup
            $table->index(['id', 'status', 'is_published'], 'idx_tracks_stream');
            $table->index('artist_id', 'idx_tracks_artist');
        });


        Schema::create('track_renditions', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignUuid('track_id')
                ->constrained('tracks')
                ->onDelete('cascade');

            $table->string('quality_label', 50);
            $table->string('format', 20);
            $table->integer('bitrate_kbps');
            $table->string('file_key', 500);
            $table->bigInteger('file_size_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_renditions');
        Schema::dropIfExists('tracks');
    }
};
