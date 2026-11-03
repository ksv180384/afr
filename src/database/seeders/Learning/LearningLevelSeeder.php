<?php

namespace Database\Seeders\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Models\Learning\LearningLevel;
use Illuminate\Database\Seeder;

class LearningLevelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LevelCode::cases() as $code) {
            $level = LearningLevel::query()->firstOrCreate(
                ['code' => $code->value],
                ['sort_order' => $code->rank(), 'title' => $code->title()],
            );

            if ($level->sort_order !== $code->rank()) {
                $level->update(['sort_order' => $code->rank()]);
            }
        }
    }
}
