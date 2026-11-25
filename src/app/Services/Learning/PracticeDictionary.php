<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningRunOperation;
use App\Models\Learning\LearningSession;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\PracticeLexeme;
use App\Models\Learning\SessionLexeme;
use App\Models\Learning\VocabularyRunEffect;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Support\Facades\DB;

final class PracticeDictionary
{
    private const FIELDS = ['fr' => true, 'ru' => true, 'transcription_ru' => true];

    public static function detachSiteDictionary(array $payload): array
    {
        foreach ($payload['vocabulary'] ?? [] as $i => $word) {
            if (isset($word['dictionary_word_id'])) {
                $payload['vocabulary'][$i]['dictionary_word_id'] = null;
            }
        }

        return $payload;
    }

    public static function expand(int $sessionId, array $payload): array
    {
        $keys = $payload['vocabulary'] ?? [];
        if (! $keys || is_array($keys[0])) {
            return $payload;
        }
        $entries = SessionLexeme::with('lexeme')->where('session_id', $sessionId)->get()->keyBy('lexeme_key');
        $payload['vocabulary'] = array_map(fn ($key) => $entries->get($key)->content(), $keys);

        return $payload;
    }

    public function packageBaseline(array $sessions): array
    {
        if (count($sessions) < 2) {
            return [];
        }
        $keys = [];
        foreach ($sessions as $lesson) {
            foreach ($lesson['vocabulary'] as $word) {
                $keys[$word['lexeme_key']] = true;
            }
        }
        $cards = [];
        foreach (array_chunk(array_keys($keys), 500) as $chunk) {
            $cards += PracticeLexeme::whereIn('lexeme_key', $chunk)->get()->pluck('content', 'lexeme_key')->all();
        }

        return $cards;
    }

    public function alignImportedWords(array $words, array $baseline): array
    {
        if (! $baseline) {
            return $words;
        }
        $current = PracticeLexeme::whereIn('lexeme_key', array_column($words, 'lexeme_key'))->get()->keyBy('lexeme_key');
        foreach ($words as $i => $word) {
            $key = $word['lexeme_key'];
            foreach ($baseline[$key] ?? [] as $field => $old) {
                $new = $current->get($key)?->content[$field] ?? $old;
                if ($old === $new) {
                    continue;
                }
                if ($word[$field] === $old) {
                    // Another lesson in this package has just corrected this shared field.
                    $words[$i][$field] = $new;
                } elseif ($word[$field] !== $new) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'vocabulary' => "В одном пакете указаны разные исправления поля {$field} у выражения {$key}.",
                    ]);
                }
            }
        }

        return $words;
    }

    /** Called inside the same transaction as the lesson write. */
    public function sync(int $sessionId, array $words, bool $published): void
    {
        $existing = SessionLexeme::with('lexeme')->where('session_id', $sessionId)->get()->keyBy('lexeme_key');
        $sharedChanges = [];
        $affected = [];
        foreach ($words as $word) {
            $key = $word['lexeme_key'];
            $core = array_intersect_key($word, self::FIELDS);
            $context = array_diff_key($word, self::FIELDS, ['lexeme_key' => true]);
            $entry = $existing->pull($key);
            if ($entry && $entry->content() == $word) {
                continue;
            }
            $card = $entry?->lexeme ?? PracticeLexeme::firstOrCreate(['lexeme_key' => $key], ['content' => $core]);
            $common = $card->content;
            foreach ($core as $field => $value) {
                if (($common[$field] ?? null) === $value) {
                    continue;
                }
                if ($published && $entry && ! array_key_exists($field, $entry->context)) {
                    $common[$field] = $value;
                    $sharedChanges[$key] = true;
                } else {
                    // An independently authored variant belongs to this lesson.
                    $context[$field] = $value;
                }
            }
            if ($common !== $card->content) {
                $card->update(['content' => $common]);
            }
            if ($entry) {
                if (($entry->context['review_items'] ?? []) != ($context['review_items'] ?? [])) {
                    $affected[] = $entry->id;
                }
                $entry->update(['context' => $context]);
            } else {
                SessionLexeme::create(['session_id' => $sessionId, 'lexeme_key' => $key, 'context' => $context]);
            }
        }
        $this->refreshChecksums($sessionId, array_keys($sharedChanges));
        $this->remove($existing->values()->all(), $affected);
    }

    public function publish(int $sessionId): void
    {
        $changed = [];
        foreach (SessionLexeme::with('lexeme')->where('session_id', $sessionId)->get() as $entry) {
            $fields = array_intersect_key($entry->context, self::FIELDS);
            if ($fields) {
                $entry->lexeme->update(['content' => $fields + $entry->lexeme->content]);
                $entry->update(['context' => array_diff_key($entry->context, self::FIELDS)]);
                $changed[] = $entry->lexeme_key;
            }
        }
        $this->refreshChecksums($sessionId, $changed);
    }

    private function refreshChecksums(int $sessionId, array $keys): void
    {
        if (! $keys) {
            return;
        }
        $lessons = SessionLexeme::whereIn('lexeme_key', $keys)->where('session_id', '!=', $sessionId)->select('session_id');
        foreach (LearningSession::whereIn('id', $lessons)->lazyById(100) as $lesson) {
            DB::table('learning_sessions')->where('id', $lesson->id)->update([
                'checksum' => CanonicalJson::hash($lesson->payload), 'updated_at' => now(),
            ]);
        }
    }

    public function removeSessions(array $ids): void
    {
        $this->remove(SessionLexeme::whereIn('session_id', $ids)->get(['id', 'lexeme_key'])->all());
    }

    private function remove(array $entries, array $changed = []): void
    {
        $ids = array_map(fn ($entry) => $entry->id, $entries);
        if (! $ids && ! $changed) {
            return;
        }
        $sources = DB::table('studied_vocabulary_sources')->whereIn('session_lexeme_id', [...$ids, ...$changed]);
        $runs = LearningRun::whereIn('user_id', $sources->select('user_id'))->whereIn('kind', ['review', 'vocabulary_test'])
            ->whereIn('status', ['in_progress', 'paused'])->orderBy('id')->lockForUpdate()->pluck('id');
        if ($ids) {
            $keys = array_map(fn ($entry) => $entry->lexeme_key, $entries);
            SessionLexeme::whereIntegerInRaw('id', $ids)->delete();
            PersonalVocabulary::whereIn('lexeme_key', $keys)->whereDoesntHave('sources')->delete();
            PracticeLexeme::whereIn('lexeme_key', $keys)->whereNotIn('lexeme_key', SessionLexeme::select('lexeme_key'))->delete();
        }
        foreach ($runs->chunk(100) as $ids) {
            foreach (LearningRun::whereKey($ids->values()->all())->get() as $run) {
                $this->checkReview($run);
            }
        }
    }

    public function checkReview(LearningRun $run): void
    {
        if (! in_array($run->kind, ['review', 'vocabulary_test'], true)
            || ! in_array($run->status->value, ['in_progress', 'paused'], true)) {
            return;
        }
        $items = $run->snapshot['items'] ?? [];
        $pending = $run->presentations()->whereDoesntHave('firstAttempt')->pluck('position')->all();
        if (! $pending) {
            return;
        }
        $words = PersonalVocabulary::where('user_id', $run->user_id)->whereIn('lexeme_key', array_column($items, 'review_lexeme_key'))
            ->with('sources.entry')->get()->keyBy('lexeme_key');
        foreach ($pending as $position) {
            $item = $items[$position - 1];
            $word = $words->get($item['review_lexeme_key']);
            if ($word && $word->sources->contains(fn ($source) => collect($source->entry->context['review_items'] ?? [])
                ->contains(fn ($option) => CanonicalJson::hash($option) === CanonicalJson::hash($item['task'])))) {
                continue;
            }
            $run->update(['status' => 'cancelled', 'finished_at' => now('UTC'), 'revision' => $run->revision + 1]);
            LearningAttempt::trimClosedRun($run);
            LearningRunOperation::compactCancelledReceipts($run);
            VocabularyRunEffect::compactClosedRun($run);
            break;
        }
    }
}
