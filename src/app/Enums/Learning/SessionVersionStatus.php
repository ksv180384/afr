<?php

namespace App\Enums\Learning;

enum SessionVersionStatus: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Published = 'published';
    case Archived = 'archived';
}
