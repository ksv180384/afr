<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создаёт many-to-many таблицу, переносит существующие связи и удаляет одиночный artist_id.
     */
    public function up(): void
    {
        Schema::create('player_artist_song', function (Blueprint $table) {
            $table->unsignedBigInteger('song_id');
            $table->unsignedBigInteger('artist_id');
            $table->unsignedInteger('position')->default(0);

            $table->primary(['song_id', 'artist_id']);
            $table->index(['artist_id', 'song_id']);
            $table->foreign('song_id')->references('id')->on('player_songs')->cascadeOnDelete();
            $table->foreign('artist_id')->references('id')->on('player_artists_songs')->cascadeOnDelete();
        });

        DB::table('player_songs')
            ->select(['id', 'artist_id'])
            ->whereNotNull('artist_id')
            ->orderBy('id')
            ->chunkById(500, function ($songs): void {
                $rows = $songs->map(fn ($song) => [
                    'song_id' => $song->id,
                    'artist_id' => $song->artist_id,
                    'position' => 0,
                ])->all();

                if ($rows !== []) {
                    DB::table('player_artist_song')->insertOrIgnore($rows);
                }
            });

        Schema::table('player_songs', function (Blueprint $table) {
            $table->dropForeign(['artist_id']);
            $table->dropIndex(['artist_id']);
            $table->dropColumn('artist_id');
        });
    }

    /**
     * Возвращает одиночный artist_id из первой связи каждой песни и удаляет pivot-таблицу.
     */
    public function down(): void
    {
        Schema::table('player_songs', function (Blueprint $table) {
            $table->unsignedBigInteger('artist_id')->nullable()->index()->after('id');
        });

        DB::table('player_artist_song')
            ->orderBy('song_id')
            ->orderBy('position')
            ->get()
            ->groupBy('song_id')
            ->each(function ($artists, $songId): void {
                DB::table('player_songs')
                    ->where('id', $songId)
                    ->update(['artist_id' => $artists->first()->artist_id]);
            });

        Schema::table('player_songs', function (Blueprint $table) {
            $table->foreign('artist_id')->references('id')->on('player_artists_songs');
        });

        Schema::dropIfExists('player_artist_song');
    }
};
