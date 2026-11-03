<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_runs', function (Blueprint $table): void {
            $table->index('started_at', 'lr_metrics_started_idx');
            $table->index('finished_at', 'lr_metrics_finished_idx');
            $table->index(['user_id', 'status', 'local_date'], 'lr_returning_idx');
        });
        Schema::table('learning_attempts', function (Blueprint $table): void {
            $table->index('received_at', 'la_metrics_received_idx');
        });
    }

    public function down(): void
    {
        Schema::table('learning_attempts', function (Blueprint $table): void {
            $table->dropIndex('la_metrics_received_idx');
        });
        Schema::table('learning_runs', function (Blueprint $table): void {
            $table->dropIndex('lr_metrics_started_idx');
            $table->dropIndex('lr_metrics_finished_idx');
            $table->dropIndex('lr_returning_idx');
        });
    }
};
