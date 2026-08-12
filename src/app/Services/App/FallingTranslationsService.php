<?php

namespace App\Services\App;

use App\Models\GameSession;
use App\Models\Word\Word;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FallingTranslationsService
{
    public const GAME_KEY = 'falling-translations';
    private const MAX_OPTIONS = 7;

    public function start(?int $userId): GameSession
    {
        $session = GameSession::create([
            'user_id' => $userId,
            'game_key' => self::GAME_KEY,
            'status' => 'active',
            'metadata' => ['played_word_ids' => []],
        ]);

        return $this->next($session);
    }

    public function next(GameSession $session): GameSession
    {
        abort_if($session->status !== 'active', 422, 'Игровая сессия уже завершена.');
        $metadata = $session->metadata ?? [];
        $playedIds = collect($metadata['played_word_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $optionsCount = $this->optionsCount($session);

        $words = Word::query()
            ->whereNotNull('word')->where('word', '!=', '')
            ->whereNotNull('translation')->where('translation', '!=', '')
            ->when($playedIds, fn ($query) => $query->whereNotIn('id', $playedIds))
            ->inRandomOrder()
            ->limit(max(40, $optionsCount * 6))
            ->get(['id', 'word', 'translation']);

        $target = $words->shift();
        abort_if(!$target, 422, 'В словаре недостаточно слов для игры.');

        $singleTranslation = fn (string $text) => trim(explode(',', $text, 2)[0]);
        $normalise = fn (string $text) => mb_strtolower($singleTranslation($text));
        $uniqueTranslations = [$normalise($target->translation) => $target];
        foreach ($words as $word) {
            if (count($uniqueTranslations) >= $optionsCount) break;
            $key = $normalise($word->translation);
            if ($key !== '') $uniqueTranslations[$key] ??= $word;
        }
        abort_if(count($uniqueTranslations) < $optionsCount, 422, 'В словаре недостаточно уникальных переводов для игры.');

        $metadata['played_word_ids'] = array_slice([...$playedIds, $target->id], -50);
        $metadata['question'] = [
            'id' => (string) Str::uuid(),
            'word' => ['id' => $target->id, 'text' => $target->word],
            'options' => collect($uniqueTranslations)->map(fn (Word $word) => [
                'id' => $word->id,
                'text' => $singleTranslation($word->translation),
            ])->shuffle()->values()->all(),
            'correct_option_id' => $target->id,
            'answered' => false,
        ];
        $session->update(['metadata' => $metadata]);

        return $session->fresh();
    }

    public function answer(GameSession $session, ?int $optionId, int $responseMs, int $wrongAttempts = 0): array
    {
        return DB::transaction(function () use ($session, $optionId, $responseMs, $wrongAttempts) {
            $session = GameSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_if($session->status !== 'active', 422, 'Игровая сессия уже завершена.');
            $question = data_get($session->metadata, 'question');
            abort_if(!$question || data_get($question, 'answered'), 422, 'Вопрос уже закрыт.');

            $correct = $optionId !== null && $optionId === (int) data_get($question, 'correct_option_id');
            $points = 0;
            $bonuses = ['base_points' => 0, 'speed_bonus' => 0, 'combo_bonus' => 0, 'error_penalty' => 0];
            if ($correct) {
                $bonuses = $this->pointBreakdown($session, $responseMs, $wrongAttempts);
                $points = $bonuses['total'];
                $session->score += $points;
                $session->correct_count++;
                $session->streak = $wrongAttempts > 0 ? 1 : $session->streak + 1;
                $session->best_streak = max($session->best_streak, $session->streak);
            } else {
                $session->streak = 0;
                $optionId === null ? $session->missed_count++ : $session->wrong_count++;
            }

            $metadata = $session->metadata;
            $metadata['question']['answered'] = true;
            $session->metadata = $metadata;
            $session->save();

            return [
                'correct' => $correct,
                'correct_option_id' => (int) data_get($question, 'correct_option_id'),
                'points' => $points,
                'bonuses' => $bonuses,
                'stats' => $this->stats($session),
            ];
        });
    }

    public function finish(GameSession $session): GameSession
    {
        if ($session->status === 'active') {
            $session->update(['status' => 'finished', 'finished_at' => now()]);
        }

        return $session->fresh();
    }

    public function question(GameSession $session): array
    {
        $question = data_get($session->metadata, 'question', []);
        return [
            'id' => data_get($question, 'id'),
            'word' => data_get($question, 'word'),
            'options' => data_get($question, 'options', []),
            'level' => $this->level($session),
            'duration_ms' => $this->duration($session),
        ];
    }

    public function stats(GameSession $session): array
    {
        return [
            'score' => $session->score,
            'correct_count' => $session->correct_count,
            'wrong_count' => $session->wrong_count,
            'missed_count' => $session->missed_count,
            'streak' => $session->streak,
            'best_streak' => $session->best_streak,
            'level' => $this->level($session),
        ];
    }

    private function level(GameSession $session): int
    {
        return min(10, intdiv($session->correct_count + $session->wrong_count + $session->missed_count, 4) + 1);
    }

    private function optionsCount(GameSession $session): int
    {
        return min(self::MAX_OPTIONS, 3 + intdiv($this->level($session) - 1, 2));
    }

    private function duration(GameSession $session): int
    {
        $speedStep = intdiv($this->level($session), 2);
        $speedMultiplier = 0.25 + ($speedStep * 0.15);

        return (int) round(8000 / $speedMultiplier);
    }

    private function pointBreakdown(GameSession $session, int $responseMs, int $wrongAttempts = 0): array
    {
        $remaining = max(0, min(1, 1 - ($responseMs / $this->duration($session))));
        $levelMultiplier = 1 + (($this->level($session) - 1) * 0.12);
        $basePoints = (int) round(100 * $levelMultiplier);
        $speedBonus = (int) round(150 * $remaining * $levelMultiplier);
        $comboBonus = $wrongAttempts > 0 ? 0 : min(100, $session->streak * 10);
        $errorPenalty = $wrongAttempts * 35;

        return [
            'base_points' => $basePoints,
            'speed_bonus' => $speedBonus,
            'combo_bonus' => $comboBonus,
            'error_penalty' => $errorPenalty,
            'total' => max(10, $basePoints + $speedBonus + $comboBonus - $errorPenalty),
        ];
    }
}
