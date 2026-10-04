<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement("CREATE OR REPLACE FUNCTION immutable_unaccent(text) RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT AS $$ SELECT public.unaccent('public.unaccent', \$1) $$");

        DB::statement('CREATE INDEX tracks_title_search_idx ON tracks USING gin (immutable_unaccent(lower(title)) gin_trgm_ops)');
        DB::statement("CREATE INDEX tracks_genre_search_idx ON tracks USING gin (immutable_unaccent(lower(coalesce(genre, ''))) gin_trgm_ops)");
        DB::statement('CREATE INDEX artists_name_search_idx ON artists USING gin (immutable_unaccent(lower(name)) gin_trgm_ops)');
        DB::statement('CREATE INDEX albums_title_search_idx ON albums USING gin (immutable_unaccent(lower(title)) gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS tracks_title_search_idx');
        DB::statement('DROP INDEX IF EXISTS tracks_genre_search_idx');
        DB::statement('DROP INDEX IF EXISTS artists_name_search_idx');
        DB::statement('DROP INDEX IF EXISTS albums_title_search_idx');
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
    }
};
