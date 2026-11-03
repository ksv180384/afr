<?php
require __DIR__.'/../../../vendor/autoload.php';
$app=require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$row=App\Models\Learning\LearningLevel::query()->where('code','A1')->first();
echo json_encode(['exists'=>$row!==null,'catalog'=>$row?->only(['code','title','summary','learning_outcomes'])],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
