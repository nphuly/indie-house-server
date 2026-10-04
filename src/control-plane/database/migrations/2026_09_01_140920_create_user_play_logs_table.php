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
        Schema::create('user_play_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('track_id')
                ->constrained('tracks')
                ->cascadeOnDelete();

            $table->integer('played_duration_seconds');
            $table->boolean('completed')->default(false)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->index('track_id', 'idx_play_logs_track');
        });

        DB::statement('CREATE INDEX idx_play_logs_user ON user_play_logs (user_id, created_at DESC);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_play_logs');
    }
};
