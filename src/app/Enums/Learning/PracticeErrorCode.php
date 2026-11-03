<?php

namespace App\Enums\Learning;

enum PracticeErrorCode: string
{
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case RevisionConflict = 'revision_conflict';
    case VersionConflict = 'version_conflict';
    case IdempotencyConflict = 'idempotency_conflict';
    case SessionExpired = 'session_expired';
    case ValidationFailed = 'validation_failed';
    case UnsupportedSchemaVersion = 'unsupported_schema_version';
    case UnsupportedCheckingPolicy = 'unsupported_checking_policy';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';
    case Disabled = 'disabled';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Unauthenticated => 401,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::RevisionConflict => 409,
            self::VersionConflict => 409,
            self::IdempotencyConflict => 409,
            self::SessionExpired => 419,
            self::ValidationFailed => 422,
            self::UnsupportedSchemaVersion => 422,
            self::UnsupportedCheckingPolicy => 503,
            self::RateLimited => 429,
            self::Unavailable => 503,
            self::Disabled => 503,
        };
    }
}
