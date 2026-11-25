<?php

use App\Enums\Learning\RunStatus;
use App\Models\Learning\LearningPresentation;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $presentations = LearningPresentation::query()->with('run')
            ->whereRaw("TRIM(snapshot) <> '[]'")->lazyById(250);

        foreach ($presentations as $presentation) {
            $snapshot = json_decode($presentation->getRawOriginal('snapshot'), true, 512, JSON_THROW_ON_ERROR);
            if ($snapshot === []) {
                continue;
            }
            $expected = $presentation->taskSnapshot($presentation->run->content());
            if (CanonicalJson::hash($snapshot) !== CanonicalJson::hash($expected)) {
                throw new LogicException('Learning presentation snapshot differs from pinned content: '.$presentation->id);
            }
        }

        Schema::table('learning_presentations', fn (Blueprint $table) => $table->dropColumn('snapshot'));
    }

    public function down(): void
    {
        Schema::table('learning_presentations', fn (Blueprint $table) => $table->json('snapshot')->nullable());
        DB::table('learning_presentations')->update(['snapshot' => '[]']);

        $presentations = LearningPresentation::query()->with('run')->whereHas('run', fn ($run) => $run
            ->whereNull('session_id')->whereIn('status', [RunStatus::InProgress->value, RunStatus::Paused->value]))->lazyById(250);
        foreach ($presentations as $presentation) {
            $snapshot = $presentation->taskSnapshot($presentation->run->content());
            DB::table('learning_presentations')->where('id', $presentation->id)
                ->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        }
    }
};
