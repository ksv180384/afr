<?php

use App\Models\Learning\LearningRun;
use App\Models\Learning\VocabularyRunEffect;
use App\Support\Learning\OperationReceipt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $owners = VocabularyRunEffect::query()->select('user_id')->distinct()->lazyById(250, 'user_id');
        foreach ($owners as $owner) {
            DB::transaction(function () use ($owner) {
                DB::table('users')->where('id', $owner->user_id)->lockForUpdate()->first(['id']);
                $credits = VocabularyRunEffect::where('user_id', $owner->user_id);
                (clone $credits)->where('success_applied', false)->delete();
                VocabularyRunEffect::compactClosedCredits($owner->user_id, $credits->distinct()->pluck('lexeme_key')->all());
            }, 3);
        }

        $cancelled = LearningRun::whereIn('kind', ['review', 'vocabulary_test'])->where('status', 'cancelled');
        foreach ($cancelled->select('id', 'user_id')->lazyById(250) as $run) {
            $pointer = OperationReceipt::encode(['run_id' => $run->id]);
            DB::table('learning_run_operations')->where('user_id', $run->user_id)->where('run_id', $run->id)
                ->where('receipt', '!=', $pointer)->update(['receipt' => $pointer]);
        }
    }

    public function down(): void
    {
        // The previous application can read the retained credits and run pointers.
    }
};
