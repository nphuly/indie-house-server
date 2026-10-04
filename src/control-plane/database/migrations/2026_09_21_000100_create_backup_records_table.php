<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('filename')->default('');
            $table->string('path')->default('');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('type', 20)->default('manual');
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};
