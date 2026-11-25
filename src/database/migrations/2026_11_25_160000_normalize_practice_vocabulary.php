<?php

use App\Models\Learning\SessionLexeme;
use App\Services\Learning\PracticeDictionary;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function up(): void
    {
        $collation = DB::getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin';
        if (! Schema::hasTable('learning_lexemes')) {
            Schema::create('learning_lexemes', function (Blueprint $table) use ($collation) {
                $table->string('lexeme_key', 128)->collation($collation)->primary();
                $table->json('content');
            });
        }
        if (! Schema::hasTable('learning_session_lexemes')) {
            Schema::create('learning_session_lexemes', function (Blueprint $table) use ($collation) {
                $table->id();
                $table->foreignId('session_id')->constrained('learning_sessions')->cascadeOnDelete();
                $table->string('lexeme_key', 128)->collation($collation);
                $table->foreign('lexeme_key', 'lsl_card_fk')->references('lexeme_key')->on('learning_lexemes')->restrictOnDelete();
                $table->json('context');
                $table->unique(['session_id', 'lexeme_key'], 'lsl_session_key');
                $table->unique(['id', 'session_id'], 'lsl_id_session');
            });
        }
        if (! Schema::hasColumn('personal_vocabulary_sources', 'session_lexeme_id')) {
            Schema::table('personal_vocabulary_sources', fn (Blueprint $t) => $t->unsignedBigInteger('session_lexeme_id')->nullable());
        }
        DB::transaction(function () {
            $cards = DB::table('learning_lexemes')->pluck('content', 'lexeme_key')->map(fn ($json) => json_decode($json, true))->all();
            foreach (DB::table('learning_sessions')->whereNotNull('payload')->select('id', 'payload', 'checksum')->lockForUpdate()->lazyById(50) as $row) {
                $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                $words = $payload['vocabulary'] ?? [];
                if (! $words || is_string($words[0])) {
                    continue;
                }
                $payload = PracticeDictionary::detachSiteDictionary($payload);
                $words = $payload['vocabulary'];
                $entries = [];
                foreach ($words as $word) {
                    $key = $word['lexeme_key'];
                    $core = array_intersect_key($word, array_flip(['fr', 'ru', 'transcription_ru']));
                    if (! isset($cards[$key])) {
                        $cards[$key] = $core;
                        DB::table('learning_lexemes')->insert(['lexeme_key' => $key, 'content' => $this->encode($core)]);
                    }
                    $context = array_diff_key($word, ['lexeme_key' => true]);
                    foreach ($core as $field => $value) {
                        if (($cards[$key][$field] ?? null) === $value) {
                            unset($context[$field]);
                        }
                    }
                    $entries[] = ['session_id' => $row->id, 'lexeme_key' => $key, 'context' => $this->encode($context)];
                }
                DB::table('learning_session_lexemes')->insert($entries);
                $compact = $payload;
                $compact['vocabulary'] = array_column($words, 'lexeme_key');
                if (CanonicalJson::hash(PracticeDictionary::expand($row->id, $compact)) !== CanonicalJson::hash($payload)) {
                    throw new LogicException('Could not reconstruct practice lesson '.$row->id);
                }
                $changes = ['payload' => $this->encode($compact)];
                $checksum = CanonicalJson::hash($payload);
                if ($row->checksum !== $checksum) {
                    $original = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                    if ($row->checksum !== CanonicalJson::hash($original)) {
                        throw new LogicException('Unexpected checksum on practice lesson '.$row->id);
                    }
                    $changes['checksum'] = $checksum;
                    DB::table('learning_runs')->where('session_id', $row->id)->where('content_checksum', $row->checksum)
                        ->whereIn('status', ['in_progress', 'paused'])->update(['content_checksum' => $checksum]);
                }
                DB::table('learning_sessions')->where('id', $row->id)->update($changes);
            }
            $lookup = DB::table('learning_session_lexemes as e')->join('personal_vocabulary as w', 'w.lexeme_key', '=', 'e.lexeme_key')
                ->whereColumn('w.id', 'personal_vocabulary_sources.personal_word_id')
                ->whereColumn('e.session_id', 'personal_vocabulary_sources.session_id')->select('e.id');
            DB::table('personal_vocabulary_sources')->whereNull('session_lexeme_id')->update(['session_lexeme_id' => DB::raw('('.$lookup->toSql().')')]);
            DB::table('personal_vocabulary_sources')->whereNull('session_lexeme_id')->delete();
            DB::table('personal_vocabulary')->whereNotIn('id', DB::table('personal_vocabulary_sources')->select('personal_word_id'))->delete();
        });
        if (Schema::hasColumn('personal_vocabulary_sources', 'snapshot')) {
            Schema::table('personal_vocabulary_sources', function (Blueprint $table) {
                $table->unsignedBigInteger('session_lexeme_id')->nullable(false)->change();
                $table->foreign(['session_lexeme_id', 'session_id'], 'pvs_entry_fk')->references(['id', 'session_id'])->on('learning_session_lexemes')->cascadeOnDelete();
                $table->dropColumn('snapshot');
            });
        }
        if (Schema::hasColumn('personal_vocabulary', 'snapshot')) {
            Schema::table('personal_vocabulary', function (Blueprint $table) {
                $table->foreign('lexeme_key', 'pv_practice_card_fk')->references('lexeme_key')->on('learning_lexemes')->cascadeOnDelete();
                $table->dropColumn('snapshot');
            });
        }
    }

    public function down(): void
    {
        Schema::table('personal_vocabulary', fn (Blueprint $t) => $t->json('snapshot')->nullable());
        Schema::table('personal_vocabulary_sources', fn (Blueprint $t) => $t->json('snapshot')->nullable());
        DB::transaction(function () {
            foreach (DB::table('learning_sessions')->whereNotNull('payload')->lazyById(50) as $lesson) {
                $payload = PracticeDictionary::expand($lesson->id, json_decode($lesson->payload, true, 512, JSON_THROW_ON_ERROR));
                DB::table('learning_sessions')->where('id', $lesson->id)->update(['payload' => $this->encode($payload)]);
            }
            foreach (DB::table('personal_vocabulary_sources')->orderBy('first_added_at')->orderBy('id')->cursor() as $source) {
                $word = SessionLexeme::with('lexeme')->findOrFail($source->session_lexeme_id)->content();
                unset($word['review_items']);
                $snapshot = $this->encode($word);
                DB::table('personal_vocabulary_sources')->where('id', $source->id)->update(['snapshot' => $snapshot]);
                DB::table('personal_vocabulary')->where('id', $source->personal_word_id)->whereNull('snapshot')->update(['snapshot' => $snapshot]);
            }
        });
        Schema::table('personal_vocabulary_sources', function (Blueprint $table) {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['session_lexeme_id', 'session_id'] : 'pvs_entry_fk');
            $table->dropColumn('session_lexeme_id');
            $table->json('snapshot')->nullable(false)->change();
        });
        Schema::table('personal_vocabulary', function (Blueprint $table) {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['lexeme_key'] : 'pv_practice_card_fk');
            $table->json('snapshot')->nullable(false)->change();
        });
        Schema::drop('learning_session_lexemes');
        Schema::drop('learning_lexemes');
    }
};
