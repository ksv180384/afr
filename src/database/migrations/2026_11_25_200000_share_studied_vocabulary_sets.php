<?php

use App\Services\Learning\VocabularyStudySets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORIGINAL = 'personal_vocabulary_sources_legacy';

    public function up(): void
    {
        if (! Schema::hasTable('learning_vocabulary_sets')) {
            Schema::create('learning_vocabulary_sets', function (Blueprint $t) {
                $t->increments('id');
                $t->foreignId('session_id')->constrained('learning_sessions')->cascadeOnDelete();
                $t->char('signature', 64);
                $t->unique(['session_id', 'signature'], 'lvs_signature');
            });
        }
        if (! Schema::hasTable('learning_vocabulary_set_entries')) {
            Schema::create('learning_vocabulary_set_entries', function (Blueprint $t) {
                $t->unsignedInteger('set_id');
                $t->unsignedBigInteger('session_lexeme_id');
                $t->primary(['set_id', 'session_lexeme_id']);
                $t->index(['session_lexeme_id', 'set_id'], 'lvse_entry');
                $t->foreign('set_id', 'lvse_set')->references('id')->on('learning_vocabulary_sets')->cascadeOnDelete();
                $t->foreign('session_lexeme_id', 'lvse_lexeme')->references('id')->on('learning_session_lexemes')->cascadeOnDelete();
            });
        }
        // Some historical sources have no identifiable completion, including imported guest dates.
        if (! Schema::hasTable('learning_vocabulary_legacy_studies')) {
            Schema::create('learning_vocabulary_legacy_studies', function (Blueprint $t) {
                $t->unsignedInteger('set_id');
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->dateTime('first_added_at');
                $t->primary(['set_id', 'user_id']);
                $t->foreign('set_id', 'lvls_set')->references('id')->on('learning_vocabulary_sets')->cascadeOnDelete();
            });
        }
        if (! Schema::hasColumn('learning_runs', 'vocabulary_set_id')) {
            if (DB::getDriverName() === 'sqlite') {
                // Add the nullable reference in place, keeping dependent attempts and results intact.
                DB::statement('ALTER TABLE learning_runs ADD COLUMN vocabulary_set_id INTEGER REFERENCES learning_vocabulary_sets(id) ON DELETE SET NULL');
            } else {
                Schema::table('learning_runs', fn (Blueprint $t) => $t->unsignedInteger('vocabulary_set_id')->nullable());
            }
        }
        if (! Schema::hasIndex('learning_runs', 'lr_vocabulary_owner')) {
            Schema::table('learning_runs', function (Blueprint $t) {
                $t->unique(['vocabulary_set_id', 'user_id'], 'lr_vocabulary_owner');
            });
        }
        if (DB::getDriverName() !== 'sqlite' && ! collect(Schema::getForeignKeys('learning_runs'))->contains('name', 'lr_vocabulary_set')) {
            Schema::table('learning_runs', fn (Blueprint $t) => $t->foreign('vocabulary_set_id', 'lr_vocabulary_set')
                ->references('id')->on('learning_vocabulary_sets')->nullOnDelete());
        }
        if (! Schema::hasColumn('learning_runs', 'vocabulary_source_at')) {
            Schema::table('learning_runs', fn (Blueprint $t) => $t->dateTime('vocabulary_source_at')->nullable());
        }

        $id = DB::getDriverName() === 'sqlite' ? "w.id || ':' || e.id" : "CONCAT(w.id, ':', e.id)";
        $select = "SELECT {$id} AS id, w.user_id, w.id AS personal_word_id, e.session_id, e.id AS session_lexeme_id,
                   COALESCE(r.vocabulary_source_at, r.finished_at, h.first_added_at) AS first_added_at
            FROM personal_vocabulary w
            JOIN learning_session_lexemes e ON e.lexeme_key = w.lexeme_key
            JOIN learning_vocabulary_set_entries m ON m.session_lexeme_id = e.id
            LEFT JOIN learning_runs r ON r.vocabulary_set_id = m.set_id AND r.user_id = w.user_id
                AND r.session_id = e.session_id AND r.status = 'completed' AND r.kind IN ('daily', 'extra')
            LEFT JOIN learning_vocabulary_legacy_studies h ON h.set_id = m.set_id AND h.user_id = w.user_id
            WHERE r.id IS NOT NULL OR h.set_id IS NOT NULL";

        if (Schema::hasTable('personal_vocabulary_sources')) {
            // Freeze the old writer before copying. The new writer can only finish after the view is published.
            Schema::rename('personal_vocabulary_sources', self::ORIGINAL);
        }
        if (Schema::hasTable(self::ORIGINAL)) {
            $owners = DB::table(self::ORIGINAL)->select('user_id')->distinct()->lazyById(100, 'user_id');
            foreach ($owners as $owner) {
                DB::transaction(fn () => $this->transfer($owner->user_id));
            }

            $this->verifySources(self::ORIGINAL, "({$select})");
            Schema::drop(self::ORIGINAL);
        }
        DB::statement('DROP VIEW IF EXISTS studied_vocabulary_sources');
        DB::statement("CREATE VIEW studied_vocabulary_sources AS {$select}");
    }

    private function transfer(int $userId): void
    {
        $sources = DB::table(self::ORIGINAL)->where('user_id', $userId)
            ->get(['session_id', 'first_added_at', 'session_lexeme_id'])->groupBy(['session_id', 'first_added_at']);
        foreach ($sources as $sessionId => $times) {
            foreach ($times as $date => $entries) {
                $setId = VocabularyStudySets::forEntries($sessionId, $entries->pluck('session_lexeme_id')->all());
                $runs = DB::table('learning_runs')->where('user_id', $userId)->where('session_id', $sessionId)
                    ->whereIn('kind', ['daily', 'extra'])->where('status', 'completed')->where('finished_at', $date)
                    ->where(fn ($q) => $q->whereNull('vocabulary_set_id')->orWhere('vocabulary_set_id', $setId))
                    ->limit(2)->pluck('id');
                if ($runs->count() === 1) {
                    DB::table('learning_runs')->where('id', $runs->first())->update(['vocabulary_set_id' => $setId]);
                } else {
                    DB::table('learning_vocabulary_legacy_studies')->updateOrInsert(
                        ['user_id' => $userId, 'set_id' => $setId], ['first_added_at' => $date]);
                }
            }
        }
    }

    private function verifySources(string $original, string $source): void
    {
        $unmatched = DB::table($original.' as p')->whereNotExists(function ($q) use ($source) {
            $q->selectRaw('1')->fromRaw($source.' as s');
            foreach (['user_id', 'personal_word_id', 'session_id', 'session_lexeme_id', 'first_added_at'] as $key) {
                $q->whereColumn('s.'.$key, 'p.'.$key);
            }
        })->exists();
        if ($unmatched || DB::table($original)->count() !== DB::query()->fromRaw($source.' as s')->count()) {
            throw new LogicException('The reconstructed vocabulary sources differ from the original records.');
        }
    }

    public function down(): void
    {
        Schema::create('personal_vocabulary_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('personal_word_id');
            $t->foreignId('session_id')->constrained('learning_sessions');
            $t->unsignedBigInteger('session_lexeme_id');
            $t->dateTime('first_added_at');
            $t->timestamps();
            $t->unique(['personal_word_id', 'session_id'], 'pvs_word_session_uq');
            $t->foreign(['user_id', 'personal_word_id'], 'pvs_word_owner_fk')->references(['user_id', 'id'])->on('personal_vocabulary')->cascadeOnDelete();
            $t->foreign(['session_lexeme_id', 'session_id'], 'pvs_entry_fk')->references(['id', 'session_id'])->on('learning_session_lexemes')->cascadeOnDelete();
        });
        $columns = ['user_id', 'personal_word_id', 'session_id', 'session_lexeme_id', 'first_added_at'];
        DB::table('personal_vocabulary_sources')->insertUsing($columns, DB::table('studied_vocabulary_sources')->select($columns));
        $this->verifySources('personal_vocabulary_sources', 'studied_vocabulary_sources');
        DB::statement('DROP VIEW studied_vocabulary_sources');
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('learning_runs', fn (Blueprint $t) => $t->dropForeign('lr_vocabulary_set'));
        }
        Schema::table('learning_runs', fn (Blueprint $t) => $t->dropUnique('lr_vocabulary_owner'));
        foreach (['vocabulary_set_id', 'vocabulary_source_at'] as $column) {
            DB::statement("ALTER TABLE learning_runs DROP COLUMN {$column}");
        }
        Schema::drop('learning_vocabulary_legacy_studies');
        Schema::drop('learning_vocabulary_set_entries');
        Schema::drop('learning_vocabulary_sets');
    }
};
