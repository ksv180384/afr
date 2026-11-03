<?php

use App\Enums\Learning\LearningLevel;
use App\Services\Learning\CheckingPolicy;
use App\Support\Learning\PracticeTime;

return [
    'enabled' => env('DAILY_PRACTICE_ENABLED', false),
    'operations' => [
        'telemetry' => env('DAILY_PRACTICE_TELEMETRY', true),
        'request_bytes' => 128 * 1024,
        'response_bytes' => 8 * 1024 * 1024,
        'sync_bytes' => 2 * 1024 * 1024,
        'vocabulary_page_size' => 30,
    ],
    'schema_version' => 1,
    'schema_path' => dirname(__DIR__).'/resources/learning/session-package-v1.schema.json',
    'language_pair' => 'fr-ru',
    'default_profile' => [
        'current_level' => LearningLevel::PreA1->value,
    ],
    'levels' => array_map(fn (LearningLevel $level): array => [
        'code' => $level->value,
        'title' => $level->title(),
    ], LearningLevel::cases()),
    'time' => [
        'server_timezone' => PracticeTime::SERVER_TIMEZONE,
        'guest_fallback_timezone' => PracticeTime::DEFAULT_TIMEZONE,
        'guest_fallback_message' => PracticeTime::FALLBACK_MESSAGE,
    ],
    'import' => [
        'max_bytes' => 10 * 1024 * 1024,
        'max_sessions' => 100,
        'max_json_depth' => 32,
        'max_object_properties' => 1000,
        'max_validation_errors' => 1000,
        'max_context_nodes' => 10000,
        'max_array_items' => 1000,
        'max_text_length' => 20000,
        'max_id_length' => 128,
        'preview_ttl_seconds' => 30 * 60,
    ],
    'session' => [
        'min_tasks' => 6,
        'max_tasks' => 8,
    ],
    'vocabulary_test' => [
        'max_words' => 10,
    ],
    'additional_review' => [
        'min_words' => 1,
        'max_words' => 3,
    ],
    'review' => [
        'interval_days' => [1, 3, 7, 14, 30],
        'max_interval_days' => 30,
        'initial_delay_days' => 1,
        'error_delay_days' => 0,
        'independent_successes_to_clear_error' => 3,
        'mixed_test_error_share' => 0.5,
    ],
    'checking' => [
        'current_version' => CheckingPolicy::CURRENT_VERSION,
        'supported_versions' => CheckingPolicy::SUPPORTED_VERSIONS,
    ],
    'promotion' => [
        'current_policy_version' => 1,
        // Pin the version and a snapshot to enrollment; missing curriculum disables promotion.
        'policies' => [
            1 => [
                'required_objectives_percent' => 80,
                'distinct_tasks_per_objective' => 2,
                'distinct_runs_per_objective' => 2,
                'distinct_checkpoint_sessions' => 2,
                'distinct_local_checkpoint_days' => 2,
                'checkpoint_score_percent' => 80,
                'required_skill' => 'vocabulary',
                'min_distinct_skills' => 2,
                'allow_technical_skips' => false,
                'requires_published_curriculum' => true,
                'requires_published_checkpoints' => true,
            ],
        ],
    ],
    // Editorial targets only: never use them as database/import-total limits.
    'content_targets' => [
        'sessions_per_level' => 100,
        'total_sessions' => 700,
    ],
];
