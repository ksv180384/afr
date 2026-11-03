<?php

namespace App\Services\Learning;

use App\Enums\Learning\LearningLevel;
use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\GuestSyncBatch;
use App\Models\Learning\GuestSyncEpisode;
use App\Models\Learning\LearningLevelEvent;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use App\Support\Learning\PracticeTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuestSyncService
{
    public function __construct(private GuestSyncLedger $ledger, private GuestRunImporter $importer, private GuestImportContext $context, private GuestVocabularyProjector $projector) {}

    public function sync(User $actor, array $input): array
    {
        $hash = CanonicalJson::hash($input);
        try {
            return DB::transaction(function () use ($actor, $input, $hash) {
                User::query()->lockForUpdate()->findOrFail($actor->id);
                $episode = GuestSyncEpisode::where('guest_id', $input['guest_id'])->lockForUpdate()->first();
                if ($episode && $episode->owner_user_id !== $actor->id) {
                    throw new AnswerCheckingException(PracticeErrorCode::Forbidden, 'Гостевой набор уже связан с другим аккаунтом.');
                }
                if (! $episode) {
                    $profile = LearningProfile::where('user_id', $actor->id)->first();
                    $episode = GuestSyncEpisode::create(['guest_id' => $input['guest_id'], 'owner_user_id' => $actor->id, 'status' => 'pending',
                        'baseline_profile_revision' => $profile?->revision, 'baseline_current_level' => $profile?->getRawOriginal('current_level'),
                        'baseline_snapshot' => ['word_ids' => PersonalVocabulary::where('user_id', $actor->id)->pluck('id')->all()],
                        'created_word_ids' => [], 'accepted_level_event_ids' => [], 'started_at' => PracticeTime::now()]);
                }
                if (! empty($input['episode_id']) && $input['episode_id'] !== $episode->id) {
                    throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Эпизод переноса не совпадает.');
                }
                $previous = GuestSyncBatch::where('guest_id', $episode->guest_id)->where('batch_id', $input['batch_id'])->first();
                if ($previous) {
                    if ($previous->payload_hash !== $hash) {
                        throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Пакет уже принят с другим содержимым.');
                    }

                    return $previous->receipt;
                }
                $accepted = [];
                $rejected = [];
                $mapping = [];
                $levelEvents = collect($input['level_events'] ?? [])->sortBy('sequence');
                $activeLevelId = $input['level_selection_event_id'] ?? $levelEvents->last()['client_event_id'] ?? null;
                foreach ($levelEvents as $event) {
                    try {
                        DB::transaction(function () use ($actor, $episode, $event, $activeLevelId) {
                            if ($this->ledger->previous($episode, $event)) {
                                return;
                            }
                            Validator::make($event, ['action' => 'required|in:level_selection', 'current_level' => 'required|in:PRE_A1,A1,A2,B1,B2,C1,C2', 'timezone' => 'required|timezone:all'])->validate();
                            if ($event['client_event_id'] !== $activeLevelId) {
                                $this->ledger->record($episode, $event, ['receipt' => ['applied' => false]]);

                                return;
                            }
                            $profile = LearningProfile::firstOrCreate(['user_id' => $actor->id], ['current_level' => $event['current_level'], 'timezone' => $event['timezone'], 'revision' => 1]);
                            $old = LearningLevel::from($profile->getRawOriginal('current_level'));
                            $guest = LearningLevel::from($event['current_level']);
                            $next = $old->rank() >= $guest->rank() ? $old : $guest;
                            $level = LearningLevelEvent::create(['user_id' => $actor->id, 'old_level' => $old->value, 'new_level' => $next->value, 'source' => 'guest_merge',
                                'source_event_id' => $event['client_event_id'], 'reason' => ['episode_id' => $episode->id, 'guest_level' => $guest->value], 'occurred_at' => PracticeTime::now()]);
                            $profile->update(['current_level' => $next->value, 'revision' => $profile->revision + 1]);
                            $this->ledger->record($episode, $event, ['server_level_event_id' => $level->id]);
                            $episode->update(['accepted_level_event_ids' => array_values(array_unique([...($episode->accepted_level_event_ids ?? []), $event['client_event_id']]))]);
                        });
                        $accepted[] = $event['client_event_id'];
                    } catch (AnswerCheckingException|ValidationException|\InvalidArgumentException $error) {
                        $episode->refresh();
                        $rejected[] = $this->rejection($error, ['event_ids' => [$event['client_event_id'] ?? null]]);
                    }
                }
                foreach ($input['runs'] ?? [] as $bundle) {
                    try {
                        $result = DB::transaction(fn () => $this->context->during($episode, fn () => $this->importer->import($actor, $episode, $bundle)));
                        $mapping[$result['guest_run_id']] = $result;
                        $accepted = [...$accepted, ...$result['accepted_event_ids']];
                    } catch (AnswerCheckingException|ValidationException|\InvalidArgumentException $error) {
                        $episode->refresh();
                        $rejected[] = $this->rejection($error, ['guest_run_id' => $bundle['guest_run_id'] ?? null,
                            'event_ids' => array_column($bundle['events'] ?? [], 'client_event_id')]);
                    }
                }
                foreach ($input['marks'] ?? [] as $event) {
                    try {
                        DB::transaction(function () use ($actor, $episode, $event) {
                            if ($this->ledger->previous($episode, $event)) {
                                return;
                            }
                            Validator::make($event, ['action' => 'required|in:vocabulary_mark', 'lexeme_key' => 'required|string|max:128', 'needs_review' => 'required|boolean'])->validate();
                            if (! PersonalVocabulary::where('user_id', $actor->id)->where('lexeme_key', $event['lexeme_key'])->exists()) {
                                throw new AnswerCheckingException(PracticeErrorCode::NotFound, 'Выражение ещё не перенесено.');
                            }
                            $this->ledger->record($episode, $event, ['receipt' => ['lexeme_key' => $event['lexeme_key'], 'needs_review' => (bool) $event['needs_review']]]);
                        });
                        $accepted[] = $event['client_event_id'];
                    } catch (AnswerCheckingException|ValidationException|\InvalidArgumentException $error) {
                        $rejected[] = $this->rejection($error, ['event_ids' => [$event['client_event_id'] ?? null]]);
                    }
                }
                $episode->refresh();
                $this->projector->project($episode);
                $episode->update(['status' => $rejected ? 'partial' : 'completed', 'completed_at' => $rejected ? null : PracticeTime::now()]);
                $receipt = ['owner_user_id' => $actor->id, 'guest_id' => $episode->guest_id, 'episode_id' => $episode->id, 'batch_id' => $input['batch_id'],
                    'accepted' => array_values(array_unique($accepted)), 'rejected' => $rejected, 'mapping' => $mapping,
                    'profile' => app(AccountRunService::class)->profile($actor)];
                GuestSyncBatch::create(['episode_id' => $episode->id, 'guest_id' => $episode->guest_id, 'owner_user_id' => $actor->id, 'batch_id' => $input['batch_id'],
                    'payload_hash' => $hash, 'status' => $episode->status, 'accepted' => $receipt['accepted'], 'rejected' => $rejected, 'receipt' => $receipt, 'received_at' => PracticeTime::now(), 'completed_at' => PracticeTime::now()]);

                return $receipt;
            }, 3);
        } catch (QueryException $error) {
            $bound = GuestSyncEpisode::where('guest_id', $input['guest_id'])->first();
            if ($bound && $bound->owner_user_id !== $actor->id) {
                throw new AnswerCheckingException(PracticeErrorCode::Forbidden, 'Гостевой набор уже связан с другим аккаунтом.');
            }
            throw $error;
        }
    }

    private function rejection(\Throwable $error, array $identity): array
    {
        return [...$identity, 'code' => $error instanceof AnswerCheckingException ? $error->errorCode->value : 'validation_failed',
            'message' => $error instanceof ValidationException ? 'Неверные данные гостевого прохождения.' : $error->getMessage()];
    }
}
