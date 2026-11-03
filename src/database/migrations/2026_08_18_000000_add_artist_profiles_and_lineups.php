<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_artists_songs', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('name');
            $table->text('description')->nullable()->after('image_path');
        });

        Schema::create('player_artist_lineups', function (Blueprint $table) {
            $table->id();
            $table->string('signature', 512)->unique();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('player_artist_lineup_members', function (Blueprint $table) {
            $table->foreignId('artist_lineup_id')->constrained('player_artist_lineups')->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained('player_artists_songs')->cascadeOnDelete();
            $table->primary(['artist_lineup_id', 'artist_id']);
        });

        Schema::table('player_songs', function (Blueprint $table) {
            $table->foreignId('artist_lineup_id')
                ->nullable()
                ->after('id')
                ->constrained('player_artist_lineups')
                ->nullOnDelete();
        });

        $artistsBySong = DB::table('player_artist_song')
            ->orderBy('song_id')
            ->orderBy('artist_id')
            ->get(['song_id', 'artist_id'])
            ->groupBy('song_id');

        foreach ($artistsBySong as $songId => $members) {
            $artistIds = $members->pluck('artist_id')->map(fn ($id) => (int) $id)->sort()->values();
            if ($artistIds->count() < 2) {
                continue;
            }

            $signature = $artistIds->implode(':');
            $lineup = DB::table('player_artist_lineups')->where('signature', $signature)->first();
            $lineupId = $lineup?->id ?? DB::table('player_artist_lineups')->insertGetId([
                'signature' => $signature,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('player_artist_lineup_members')->insertOrIgnore(
                $artistIds->map(fn (int $artistId) => [
                    'artist_lineup_id' => $lineupId,
                    'artist_id' => $artistId,
                ])->all(),
            );

            DB::table('player_songs')->where('id', $songId)->update(['artist_lineup_id' => $lineupId]);
        }
    }

    public function down(): void
    {
        Schema::table('player_songs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('artist_lineup_id');
        });

        Schema::dropIfExists('player_artist_lineup_members');
        Schema::dropIfExists('player_artist_lineups');

        Schema::table('player_artists_songs', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'description']);
        });
    }
};
