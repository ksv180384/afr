<?php

namespace App\Services\Learning;

use Illuminate\Support\Facades\Log;

final class PracticeTelemetry
{
    public function record(string $event, array $context = []): void
    {
        if (! config('daily_practice.operations.telemetry')) {
            return;
        }
        $allowed = array_intersect_key($context, array_flip(['route', 'method', 'status', 'duration_ms', 'response_bytes', 'rejected_count', 'error_code', 'error_class', 'error_file', 'error_line', 'deleted_files']));
        try {
            Log::channel('daily_practice')->log(($allowed['status'] ?? 200) >= 400 ? 'warning' : 'info', $event, $allowed);
        } catch (\Throwable) {
            // Observability failure must not turn a committed write into an unacknowledged error.
        }
    }
}
