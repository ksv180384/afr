<?php

use App\Enums\Learning\LearningLevel;
use App\Enums\Learning\LearningSkill;
use App\Enums\Learning\PracticeErrorCode;
use App\Enums\Learning\RunStatus;
use App\Enums\Learning\SessionKind;
use App\Enums\Learning\SessionVersionStatus;
use App\Enums\Learning\TaskType;
use App\Services\Learning\CheckingPolicy;
use App\Support\Learning\PracticeTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Env;

function dailyPracticeContract(): array
{
    return json_decode(file_get_contents(__DIR__.'/../../resources/js/App/Learning/types/contract.json'), true, 512, JSON_THROW_ON_ERROR);
}

function dailyPracticeConfig(): array
{
    return require __DIR__.'/../../config/daily_practice.php';
}

it('keeps client codes identical to server enums and settings', function () {
    $contract = dailyPracticeContract();
    $config = dailyPracticeConfig();

    expect($contract['levels'])->toBe($config['levels'])
        ->and($contract['default_profile'])->toBe($config['default_profile'])
        ->and($contract['schema_version'])->toBe($config['schema_version'])
        ->and($contract['time']['server_timezone'])->toBe(PracticeTime::SERVER_TIMEZONE)
        ->and($contract['time']['guest_fallback_message'])->toBe(PracticeTime::FALLBACK_MESSAGE)
        ->and($contract['time'])->toBe($config['time']);

    foreach ([
        'version_statuses' => SessionVersionStatus::class,
        'run_statuses' => RunStatus::class,
        'task_types' => TaskType::class,
        'skills' => LearningSkill::class,
        'session_kinds' => SessionKind::class,
    ] as $key => $enum) {
        expect($contract[$key])->toBe(array_column($enum::cases(), 'value'));
    }

    $errors = [];
    foreach (PracticeErrorCode::cases() as $error) {
        $errors[$error->value] = $error->httpStatus();
    }
    expect($contract['errors'])->toBe($errors);
});

it('preserves ordered levels and ends the progression at C2', function () {
    expect(array_column(LearningLevel::cases(), 'value'))->toBe(['PRE_A1', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2'])
        ->and(LearningLevel::PreA1->title())->toBe('С нуля')
        ->and(LearningLevel::PreA1->next())->toBe(LearningLevel::A1)
        ->and(LearningLevel::B1->rank())->toBeGreaterThan(LearningLevel::A2->rank())
        ->and(LearningLevel::C2->next())->toBeNull()
        ->and(LearningLevel::tryFrom('A0'))->toBeNull()
        ->and(array_keys(dailyPracticeConfig()['default_profile']))->toBe(['current_level']);
});

it('supports explicitly pinned immutable checking policies without a latest-version fallback', function () {
    $contract = dailyPracticeContract();
    $config = dailyPracticeConfig();

    expect($contract['checking']['current_version'])->toBe($config['checking']['current_version'])
        ->and(array_keys($contract['checking']['policies']))->toBe($config['checking']['supported_versions']);

    foreach ($config['checking']['supported_versions'] as $version) {
        $policy = CheckingPolicy::forVersion($version);
        expect($policy->version)->toBe($version)
            ->and($policy->rules)->toBe($contract['checking']['policies'][$version]);
    }

    $policy = CheckingPolicy::forVersion(1);
    expect(fn () => $policy->rules['completed_results_immutable'] = false)->toThrow(Error::class)
        ->and(CheckingPolicy::forVersion(1)->rules['completed_results_immutable'])->toBeTrue()
        ->and(fn () => CheckingPolicy::forVersion(0))->toThrow(DomainException::class)
        ->and(fn () => CheckingPolicy::forVersion(2))->toThrow(DomainException::class);
});

it('keeps opt-in settings cacheable without conflating content targets and limits', function () {
    $repository = Env::getRepository();
    $previous = $repository->get('DAILY_PRACTICE_ENABLED');

    try {
        $repository->clear('DAILY_PRACTICE_ENABLED');
        expect(dailyPracticeConfig()['enabled'])->toBeFalse();
        $repository->set('DAILY_PRACTICE_ENABLED', 'true');
        expect(dailyPracticeConfig()['enabled'])->toBeTrue();
        $repository->set('DAILY_PRACTICE_ENABLED', 'false');
        $config = dailyPracticeConfig();

        expect($config['enabled'])->toBeFalse()
            ->and(eval('return '.var_export($config, true).';'))->toBe($config)
            ->and($config['content_targets']['sessions_per_level'] * count(LearningLevel::cases()))->toBe($config['content_targets']['total_sessions'])
            ->and($config['import']['max_bytes'])->toBe(10485760)
            ->and($config['session'])->toBe(['min_tasks' => 6, 'max_tasks' => 8])
            ->and($config['vocabulary_test']['max_words'])->toBe(10)
            ->and($config['additional_review'])->toBe(['min_words' => 1, 'max_words' => 3])
            ->and($config['review']['interval_days'])->toBe([1, 3, 7, 14, 30])
            ->and($config['promotion']['policies'][1]['checkpoint_score_percent'])->toBe(80);
    } finally {
        $previous === null ? $repository->clear('DAILY_PRACTICE_ENABLED') : $repository->set('DAILY_PRACTICE_ENABLED', $previous);
    }
});

it('uses UTC instants and the timezone pinned to the run across midnight and DST', function () {
    $vectors = json_decode(file_get_contents(__DIR__.'/../Fixtures/daily-practice-time.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($vectors as $vector) {
        $instant = new DateTimeImmutable($vector['instant']);
        expect(PracticeTime::localDate($instant, $vector['timezone']))->toBe($vector['date']);
        expect($instant->format('Y-m-d\TH:i:sP'))->toBe((new DateTimeImmutable($vector['instant']))->format('Y-m-d\TH:i:sP'));
    }

    CarbonImmutable::setTestNow(new DateTimeImmutable('2026-10-24T23:30:00+03:00'));
    try {
        expect(PracticeTime::now()->getTimezone()->getName())->toBe('UTC')
            ->and(PracticeTime::now()->format('Y-m-d H:i'))->toBe('2026-10-24 20:30');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('rejects invalid zones and numeric offsets instead of silently changing account dates', function (string $timezone) {
    expect(fn () => PracticeTime::localDate(new DateTimeImmutable('2026-10-24T21:30:00Z'), $timezone))
        ->toThrow(InvalidArgumentException::class);
})->with(['', '+03:00', 'Mars/Olympus', 'MSK']);
