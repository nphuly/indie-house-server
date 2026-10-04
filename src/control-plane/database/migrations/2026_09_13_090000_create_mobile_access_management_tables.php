<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stream_accesses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('high_quality_enabled')->default(true);
            $table->timestamp('streaming_blocked_until')->nullable()->index();
            $table->string('streaming_block_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('mobile_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('previous_token_hash', 64)->nullable()->index();
            $table->string('password_fingerprint', 64);
            $table->string('device_name', 120)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoked_reason', 120)->nullable();
            $table->timestamp('replay_detected_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'expires_at']);
        });

        Schema::create('jwt_signing_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kid', 120)->unique();
            $table->string('public_key_fingerprint', 64);
            $table->string('status', 16)->default('staged')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mobile_auth_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mobile_session_id')->nullable()->constrained('mobile_sessions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80)->index();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_auth_events');
        Schema::dropIfExists('jwt_signing_keys');
        Schema::dropIfExists('mobile_sessions');
        Schema::dropIfExists('user_stream_accesses');
    }
};
