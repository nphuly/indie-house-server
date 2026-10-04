<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code_hash', 64)->unique();
            $table->string('email')->nullable()->index();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('invite_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invite_id')->constrained('invites')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['invite_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_redemptions');
        Schema::dropIfExists('invites');
    }
};
