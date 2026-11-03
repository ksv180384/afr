<?php
require __DIR__.'/../../../vendor/autoload.php';
$app=require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$l=App\Models\Learning\LearningLevel::query()->where('code','A2')->first();
echo json_encode($l?->only(['code','title','summary','learning_outcomes','published_at']),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
