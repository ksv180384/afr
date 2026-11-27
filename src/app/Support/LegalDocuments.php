<?php

namespace App\Support;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;

final class LegalDocuments
{
    public const VERSION = '2026-11-27';

    public const TITLES = [
        'privacy' => 'Политика конфиденциальности',
        'terms' => 'Правила сайта',
        'consent' => 'Согласие на обработку персональных данных',
    ];

    private const AGREEMENTS = ['terms', 'consent'];

    public const RULES = [
        'confirmation_rules' => 'accepted',
        'personal_data_consent' => 'accepted',
        'legal_version' => 'required|in:'.self::VERSION,
    ];

    public const MESSAGES = [
        'confirmation_rules.accepted' => 'Для продолжения примите правила сайта.',
        'personal_data_consent.accepted' => 'Для продолжения дайте согласие на обработку персональных данных.',
        'legal_version.required' => 'Обновите страницу, чтобы прочитать действующие документы.',
        'legal_version.in' => 'Документы обновились. Обновите страницу и ознакомьтесь с новой редакцией.',
    ];

    public static function acceptedBy(User $user): bool
    {
        return DB::table('user_legal_acceptances')
            ->where('user_id', $user->getKey())
            ->where('version', self::VERSION)
            ->whereIn('document', self::AGREEMENTS)
            ->count() === count(self::AGREEMENTS);
    }

    public static function record(User $user): void
    {
        $rows = [];
        $time = now('UTC');
        foreach (self::AGREEMENTS as $document) {
            $rows[] = [
                'user_id' => $user->getKey(),
                'document' => $document,
                'version' => self::VERSION,
                'accepted_at' => $time,
            ];
        }

        // Retried submissions keep the original acceptance time.
        DB::table('user_legal_acceptances')->upsert($rows, ['user_id', 'document', 'version'], ['version']);
    }
}
