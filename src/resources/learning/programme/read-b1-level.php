<?php
require __DIR__.'/../../../vendor/autoload.php';
$app=require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(Illuminate\Support\Facades\DB::table('learning_levels')->where('code','B1')->first(),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
