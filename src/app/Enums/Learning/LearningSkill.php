<?php

namespace App\Enums\Learning;

enum LearningSkill: string
{
    case Vocabulary = 'vocabulary';
    case Listening = 'listening';
    case Reading = 'reading';
    case Grammar = 'grammar';
    case SentenceBuilding = 'sentence_building';
}
