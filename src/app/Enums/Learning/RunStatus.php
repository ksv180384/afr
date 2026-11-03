<?php

namespace App\Enums\Learning;

enum RunStatus: string
{
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Completed = 'completed';
}
