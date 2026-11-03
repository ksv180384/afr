<?php

namespace App\Enums\Learning;

enum TaskType: string
{
    case SingleChoice = 'single_choice';
    case MatchingPairs = 'matching_pairs';
    case FillBlank = 'fill_blank';
    case WordOrder = 'word_order';
    case ShortAnswer = 'short_answer';
}
