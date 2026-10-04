<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive and off by default: the running release ignores the column, and no
// listener gains lossless until an admin grants it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_stream_accesses', function (Blueprint $table): void {
            $table->boolean('lossless_enabled')->default(false)->after('high_quality_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('user_stream_accesses', function (Blueprint $table): void {
            $table->dropColumn('lossless_enabled');
        });
    }
};
