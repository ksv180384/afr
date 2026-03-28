<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;

class SendTelegramLogJob
{
    use Dispatchable;

    public function __construct(
        private readonly string $message,
    ) {}

    public function handle(): void
    {
        logger()
            ->channel('telegram')
            ->alert($this->message);
    }
}
