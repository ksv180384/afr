<?php

namespace App\Services\Learning;

use DomainException;

final readonly class CheckingPolicy
{
    public const CURRENT_VERSION = 1;

    public const SUPPORTED_VERSIONS = [1];

    private function __construct(
        public int $version,
        public array $rules,
    ) {}

    public function runAttributes(): array
    {
        return ['checking_policy_version' => $this->version, 'checking_policy_snapshot' => $this->rules];
    }

    /**
     * Resolve the version pinned to a run, never the latest default.
     * Keep each released version unchanged when adding a new checker.
     */
    public static function forVersion(int $version): self
    {
        return match ($version) {
            1 => new self(1, [
                'normalization' => 'unicode_nfc_v1',
                'scoring' => 'first_independent_success_v1',
                'preserve_diacritics' => true,
                'preserve_internal_punctuation' => true,
                'help_counts_as_independent_success' => false,
                'correction_changes_first_score' => false,
                'technical_skip_counts_as_language_error' => false,
                'completed_results_immutable' => true,
            ]),
            default => throw new DomainException('Unsupported checking policy version: '.$version),
        };
    }
}
