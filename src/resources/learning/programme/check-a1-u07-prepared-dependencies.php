<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$current = json_decode(file_get_contents(__DIR__.'/packages/A1/A1-U07.v1.json'), true, 512, JSON_THROW_ON_ERROR);
$prior = json_decode(file_get_contents(__DIR__.'/packages/A1/A1-U06.v1.json'), true, 512, JSON_THROW_ON_ERROR);
$current['package_id']='validation-only-a1-u06-u07-prepared-dependencies';
$current['units']=array_merge($prior['units'],$current['units']);
$current['sessions']=array_merge($prior['sessions'],$current['sessions']);
$report=$app->make(App\Services\Learning\SessionPackageValidator::class)->validate(json_encode($current, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))->toArray();
echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
