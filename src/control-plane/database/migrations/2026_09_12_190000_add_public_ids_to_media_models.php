<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const array TABLES = ['artists', 'albums', 'tracks', 'playlists'];

    private const int PUBLIC_ID_LENGTH = 12;

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('public_id', self::PUBLIC_ID_LENGTH)->nullable()->after('id');
            });
        }

        foreach (self::TABLES as $table) {
            foreach (DB::table($table)->whereNull('public_id')->select('id')->get() as $record) {
                DB::table($table)->where('id', $record->id)->update([
                    'public_id' => $this->generatePublicId($table),
                ]);
            }

            DB::statement("ALTER TABLE {$table} ALTER COLUMN public_id SET NOT NULL");

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique('public_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique(['public_id']);
                $blueprint->dropColumn('public_id');
            });
        }
    }

    private function generatePublicId(string $table): string
    {
        do {
            $publicId = Str::random(self::PUBLIC_ID_LENGTH);
        } while (DB::table($table)->where('public_id', $publicId)->exists());

        return $publicId;
    }
};
