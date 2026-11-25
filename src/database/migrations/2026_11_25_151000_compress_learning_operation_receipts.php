<?php

use App\Support\Learning\OperationReceipt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $operations = DB::table('learning_run_operations')->select('id', 'receipt')
            ->whereRaw('LENGTH(receipt) >= ?', [OperationReceipt::MIN_COMPRESSION_BYTES])->lazyById(250);
        foreach ($operations as $operation) {
            $receipt = OperationReceipt::encode(OperationReceipt::decode($operation->receipt));
            if (strlen($receipt) < strlen($operation->receipt)) {
                DB::table('learning_run_operations')->where('id', $operation->id)->where('receipt', $operation->receipt)
                    ->update(['receipt' => $receipt]);
            }
        }
    }

    public function down(): void
    {
        $operations = DB::table('learning_run_operations')->select('id', 'receipt')->whereNotNull('receipt->gzip_v1')->lazyById(250);
        foreach ($operations as $operation) {
            $receipt = json_encode(OperationReceipt::decode($operation->receipt), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            DB::table('learning_run_operations')->where('id', $operation->id)->where('receipt', $operation->receipt)
                ->update(['receipt' => $receipt]);
        }
    }
};
