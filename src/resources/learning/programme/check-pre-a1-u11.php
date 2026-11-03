<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path=__DIR__.'/packages/PRE_A1/PRE_A1-U11.v1.json';
$report=$app->make(App\Services\Learning\SessionPackageValidator::class)->validate(file_get_contents($path))->toArray();
echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
