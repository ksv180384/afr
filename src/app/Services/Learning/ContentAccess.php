<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\LearningEditorialEvent;
use App\Models\User\User;

final class ContentAccess
{
    public const REVIEW_CHECKS = ['language', 'answers', 'level', 'pronunciation', 'mixed_text', 'audio'];

    public function check(?User $actor): User
    {
        if ($actor === null) {
            throw new ContentOperationException(PracticeErrorCode::Unauthenticated, 'Войдите в аккаунт.');
        }
        $fresh = User::query()->with('rang')->find($actor->id);
        if (! $fresh || ! $fresh->hasVerifiedEmail() || $fresh->rang?->alias !== 'administrator') {
            throw new ContentOperationException(PracticeErrorCode::Forbidden, 'Требуется подтверждённый аккаунт администратора.');
        }

        return $fresh;
    }

    public function review(array $checks): void
    {
        foreach (self::REVIEW_CHECKS as $key) {
            if (($checks[$key] ?? false) !== true) {
                throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Подтвердите все пункты редакторской проверки.', ['missing_check' => $key]);
            }
        }
    }

    public function audit(User $actor, string $operation, array $target, array $before = [], array $after = []): void
    {
        LearningEditorialEvent::create([
            ...$target, 'actor_id' => $actor->id, 'operation' => $operation,
            'previous_state' => $before, 'new_state' => $after, 'occurred_at' => now('UTC'),
        ]);
    }

    public function validateReport(\App\DTOs\Learning\PackageValidationReport $report): array
    {
        if (! $report->isValid()) {
            $conflict = collect($report->errors())->contains(fn ($issue): bool => in_array($issue->code, ['version_conflict', 'session_identity_conflict', 'catalog_conflict'], true));
            throw new ContentOperationException($conflict ? PracticeErrorCode::VersionConflict : PracticeErrorCode::ValidationFailed, 'Пакет не прошёл повторную проверку.', $report->toArray());
        }

        return $report->package();
    }
}
