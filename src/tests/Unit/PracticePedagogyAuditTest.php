<?php

require_once __DIR__.'/../../scripts/learning/PedagogyAudit.php';

function pedagogyPackage(): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/Learning/pre-a1-u01-examples.json'), true, 512, JSON_THROW_ON_ERROR);
}

function pedagogyWarnings(array $package, array $context = [], bool $new = true): array
{
    return (new PracticeAudit\PedagogyAudit)->analyze($package, $context, $new)['warnings'];
}

test('audit normalizes apostrophes spaces case and preserves input arrays', function () {
    expect(PracticeAudit\PedagogyAudit::normalize("  J’AI\t UN   LIVRE "))->toBe("j'ai un livre");
    $package = pedagogyPackage();
    $original = $package;
    $report = (new PracticeAudit\PedagogyAudit)->analyze($package);
    expect($package)->toBe($original)->and($report['language_quality'])->toBe('not_assessed')
        ->and($report['learning_effectiveness'])->toBe('not_measured');
});

test('audit detects checkpoint reuse of long sources and absence of new exact contexts', function () {
    $prior = pedagogyPackage();
    $prior['sessions'] = [$prior['sessions'][0]];
    $prior['sessions'][0]['tasks'][0]['prompt']['audio']['text'] = 'Bonjour, je voudrais un café avec du lait.';
    $target = $prior;
    $target['sessions'][0]['id'] = 'target';
    $target['sessions'][0]['kind'] = 'checkpoint';
    $warnings = pedagogyWarnings($target, [$prior]);
    expect(array_column($warnings, 'code'))->toContain('checkpoint_source_reuse', 'repeated_question', 'no_new_source_context');
    $match = array_values(array_filter($warnings, fn ($w) => $w['code'] === 'checkpoint_source_reuse'))[0];
    expect($match['task_id'])->toBe('Q01')->and($match['evidence']['previous']['session_id'])->toBe($prior['sessions'][0]['id']);
});

test('short common source matches are explicitly low confidence', function () {
    $prior = pedagogyPackage();
    $prior['sessions'] = [$prior['sessions'][0]];
    $prior['sessions'][0]['tasks'] = [$prior['sessions'][0]['tasks'][0]];
    $target = $prior;
    $target['sessions'][0]['kind'] = 'checkpoint';
    $warnings = pedagogyWarnings($target, [$prior]);
    expect(array_column($warnings, 'code'))->toContain('common_short_source_reuse')->not->toContain('checkpoint_source_reuse');
    $short = array_values(array_filter($warnings, fn ($w) => $w['code'] === 'common_short_source_reuse'))[0];
    expect($short['confidence'])->toBe('low_short_phrase');
});

test('different matching cards are not reported as an identical question', function () {
    $prior = pedagogyPackage();
    $prior['sessions'] = [$prior['sessions'][0]];
    $prior['sessions'][0]['tasks'] = [$prior['sessions'][0]['tasks'][1]];
    $target = $prior;
    $target['sessions'][0]['tasks'][0]['left_items'][0]['text'] = 'un autre mot';
    expect(array_column(pedagogyWarnings($target, [$prior]), 'code'))->not->toContain('repeated_question');
});

test('answer length position and repetition cues are reported with task evidence', function () {
    $package = pedagogyPackage();
    $package['sessions'] = [$package['sessions'][0]];
    $task = $package['sessions'][0]['tasks'][0];
    foreach ($task['choices'] as &$choice) {
        $choice['text'] = $choice['id'] === $task['correct_choice_id'] ? 'Этот ответ значительно длиннее двух остальных коротких вариантов' : 'Нет';
    }
    unset($choice);
    $package['sessions'][0]['tasks'] = [];
    for ($i = 1; $i <= 6; $i++) {
        $task['id'] = 'Q'.$i;
        $package['sessions'][0]['tasks'][] = $task;
    }
    expect(array_column(pedagogyWarnings($package), 'code'))->toContain('correct_option_length_cue', 'correct_option_position_bias', 'repeated_correct_answer');
});

test('required expressions need review variants and prior introduction links are only a metadata signal', function () {
    $package = pedagogyPackage();
    $package['sessions'][0]['introductions'] = [];
    $package['sessions'][0]['vocabulary'][0]['review_items'] = [];
    expect(array_column(pedagogyWarnings($package), 'code'))->toContain('assessment_without_prior_intro_link', 'missing_review_variant');
    $package['sessions'][0]['kind'] = 'checkpoint';
    expect(array_column(pedagogyWarnings($package), 'code'))->toContain('checkpoint_without_prior_intro_link');
});

test('typed tasks and review items distinguish legacy support from new material rules', function () {
    $package = pedagogyPackage();
    $typed = ['type' => 'short_answer'];
    $package['sessions'][0]['vocabulary'][0]['review_items'] = [$typed];
    expect(array_column(pedagogyWarnings($package, [], true), 'code'))->toContain('typed_input_in_new_material');
    expect(array_column(pedagogyWarnings($package, [], false), 'code'))->toContain('legacy_typed_input')->not->toContain('typed_input_in_new_material');
});

test('listening without a source is identified separately from language quality', function () {
    $package = pedagogyPackage();
    unset($package['sessions'][0]['tasks'][0]['prompt']['audio']);
    expect(array_column(pedagogyWarnings($package), 'code'))->toContain('listening_source_missing');
});

test('question fingerprints ignore JSON field order but retain card content', function () {
    $prior = pedagogyPackage();
    $target = $prior;
    $target['sessions'][0]['tasks'][0] = array_reverse($target['sessions'][0]['tasks'][0], true);
    $warnings = pedagogyWarnings($target, [$prior]);
    expect(array_values(array_filter($warnings, fn ($w) => $w['code'] === 'repeated_question' && $w['task_id'] === 'Q01')))->not->toBeEmpty();
});
