<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The repair statements below are PostgreSQL-specific. A fresh SQLite
        // schema already uses the UUID column declared in the create migration.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::getColumnType('album_tracks', 'track_id') === 'uuid') {
            return;
        }

        DB::statement('ALTER TABLE album_tracks DROP CONSTRAINT album_tracks_pkey');
        DB::statement('ALTER TABLE album_tracks ALTER COLUMN track_id TYPE uuid USING track_id::text::uuid');
        DB::statement('ALTER TABLE album_tracks ADD CONSTRAINT album_tracks_pkey PRIMARY KEY (album_id, track_id)');

        DB::statement(<<<'SQL'
            ALTER TABLE album_tracks
            ADD CONSTRAINT album_tracks_album_id_foreign
            FOREIGN KEY (album_id) REFERENCES albums (id) ON DELETE CASCADE
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE album_tracks
            ADD CONSTRAINT album_tracks_track_id_foreign
            FOREIGN KEY (track_id) REFERENCES tracks (id) ON DELETE CASCADE
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('This migration cannot be safely reversed because UUID track IDs cannot be converted to bigint.');
    }
};
