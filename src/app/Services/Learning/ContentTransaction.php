<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\LearningLevel;
use App\Models\User\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ContentTransaction
{
    public function __construct(private readonly ContentAccess $access) {}

    public function run(User $actor, Closure $operation): mixed
    {
        try {
            return DB::transaction(function () use ($actor, $operation): mixed {
                // Existing fixed rows serialize catalog mutations, including IDs not yet inserted.
                $levels = LearningLevel::query()->orderBy('id')->lockForUpdate()->get();
                if ($levels->count() !== count(config('daily_practice.levels'))) {
                    throw new ContentOperationException(PracticeErrorCode::Unavailable, 'Справочник уровней не подготовлен.');
                }
                $this->access->check($actor);

                return $operation();
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Каталог изменён другим запросом; обновите preview.');
            }
            throw $exception;
        }
    }
}
