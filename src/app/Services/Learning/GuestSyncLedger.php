<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\GuestEventMapping;
use App\Models\Learning\GuestSyncEpisode;
use App\Models\Learning\LearningRunOperation;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Support\Str;

class GuestSyncLedger
{
    public function previous(GuestSyncEpisode $episode, array $event): ?GuestEventMapping
    {
        if (! Str::isUuid($event['client_event_id'] ?? '') || ! is_int($event['sequence'] ?? null) || $event['sequence'] < 1) {
            AnswerChecker::invalid('Некорректный ID или порядок гостевого события.');
        }
        $row = $episode->mappings()->where('entity_type', 'event')->where('guest_entity_id', $event['client_event_id'])->first();
        if ($row) {
            if (($row->receipt['hash'] ?? '') !== CanonicalJson::hash($event)) {
                throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Гостевой ID уже принят с другим содержимым.');
            }

            return $row;
        }
        if ($episode->mappings()->where('entity_type', 'event')->where('receipt->sequence', $event['sequence'])->exists()
            || LearningRunOperation::where('user_id', $episode->owner_user_id)->where('client_event_id', $event['client_event_id'])->exists()) {
            throw new AnswerCheckingException(PracticeErrorCode::IdempotencyConflict, 'Этот порядок или ID события уже использован.');
        }

        return null;
    }

    public function record(GuestSyncEpisode $episode, array $event, array $extra = []): GuestEventMapping
    {
        return GuestEventMapping::create(['episode_id' => $episode->id, 'guest_id' => $episode->guest_id, 'owner_user_id' => $episode->owner_user_id,
            'entity_type' => 'event', 'guest_entity_id' => $event['client_event_id'], ...$extra,
            'receipt' => ['hash' => CanonicalJson::hash($event), 'action' => $event['action'], 'sequence' => $event['sequence'],
                'client_occurred_at' => $event['client_occurred_at'] ?? null, ...($extra['receipt'] ?? [])]]);
    }
}
