<?php

use App\DTOs\Learning\PackageValidationReport;
use App\Http\Requests\Admin\DailySession\UploadSessionPackageRequest;
use App\Models\Learning\LearningMedia;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\SessionPackageValidator;
use App\Services\Learning\Validation\CanonicalJson;
use App\Services\Learning\Validation\SessionPackageSchemaValidator;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(LearningLevelSeeder::class);
});

function packageFixture(string $name = 'pre-a1-u01-examples.json'): string
{
    return file_get_contents(base_path('tests/Fixtures/Learning/'.$name));
}

function packageData(string $name = 'pre-a1-u01-examples.json'): array
{
    return json_decode(packageFixture($name), true, 512, JSON_THROW_ON_ERROR);
}

function packageCheck(array|string $data): PackageValidationReport
{
    return app(SessionPackageValidator::class)->validate(is_string($data) ? $data : json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

function packageHasIssue(PackageValidationReport $report, string $code, string $path): void
{
    $issues = $report->toArray()['errors'];
    expect(array_values(array_filter($issues, fn (array $issue): bool => $issue['code'] === $code && $issue['path'] === $path)))->not->toBeEmpty();
    expect($report->isValid())->toBeFalse()->and($report->package())->toBeNull();
}

function packageExisting(array $session, ?array $unit = null): LearningSession
{
    $unitRow = LearningUnit::query()->where('external_id', $session['unit_id'])->first()
        ?? LearningUnit::factory()->create([
            'external_id' => $session['unit_id'],
            'level' => $session['level'],
            ...($unit ? ['slug' => $unit['slug'], 'sort_order' => $unit['order']] : []),
        ]);
    $row = LearningSession::factory()->create([
        'external_id' => $session['id'],
        'level' => $session['level'],
        'unit_id' => $unitRow->id,
        'sort_order' => $session['order'],
    ]);
    LearningSessionVersion::factory()->create([
        'session_id' => $row->id,
        'version' => $session['version'],
        'payload' => $session,
        'checksum' => CanonicalJson::hash($session),
    ]);

    return $row;
}

it('accepts complete packages and keeps editorial warnings separate', function (string $file) {
    $report = packageCheck(packageFixture($file));
    expect($report->toArray()['errors'])->toBe([])
        ->and($report->isValid())->toBeTrue()
        ->and($report->package())->toBe(packageData($file))
        ->and($report->fileHash)->toBe(hash('sha256', packageFixture($file)))
        ->and($report->warnings())->not->toBeEmpty()
        ->and($report->toArray()['requires_editorial_review'])->toBeTrue()
        ->and($report->toArray())->not->toHaveKey('package');
})->with(['pre-a1-u01-examples.json', 'task-types.json']);

it('honours all ten documented negative examples with exact field context', function (array $case) {
    if (isset($case['context'])) {
        $original = packageData();
        foreach ($original['sessions'] as $session) {
            packageExisting($session, $original['units'][0]);
        }
    }
    $data = packageData('invalid/'.$case['file']);
    $report = packageCheck($data);
    foreach ($case['expected_errors'] as $expected) {
        packageHasIssue($report, $expected['code'], $expected['path']);
        $issue = collect($report->errors())->first(fn ($issue): bool => $issue->code === $expected['code'] && $issue->path === $expected['path']);
        expect($issue->message)->not->toBe('');
        if (preg_match('~/sessions/(\d+)~', $expected['path'], $match)) {
            expect($issue->sessionId)->toBe($data['sessions'][(int) $match[1]]['id']);
            if (preg_match('~/tasks/(\d+)~', $expected['path'], $task)) {
                expect($issue->taskId)->toBe($data['sessions'][(int) $match[1]]['tasks'][(int) $task[1]]['id']);
            }
        }
    }
})->with(array_map(fn (array $case): array => [$case], json_decode(file_get_contents(__DIR__.'/../Fixtures/Learning/invalid/manifest.json'), true)['cases']));

it('rejects raw JSON hazards before querying content', function (string $json, string $code, string $path) {
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    packageHasIssue(packageCheck($json), $code, $path);
    expect($queries)->toBe([]);
})->with([
    'duplicate escaped key' => ['{"a":1,"\\u0061":2}', 'duplicate_json_key', '/a'],
    'duplicate nested pointer' => ['{"a/b":{"~":1,"~":2}}', 'duplicate_json_key', '/a~1b/~0'],
    'list root' => ['[]', 'invalid_root', ''],
    'null root' => ['null', 'invalid_root', ''],
    'number root' => ['12', 'invalid_root', ''],
    'trailing garbage' => ['{} true', 'invalid_json', ''],
    'trailing comma' => ['{"a":1,}', 'invalid_json', ''],
    'missing separator' => ['{"a":1 "b":2}', 'invalid_json', ''],
    'invalid escape' => ['{"a":"\\x"}', 'invalid_json', ''],
    'infinite number' => ['{"a":1e400}', 'invalid_number', '/a'],
    'BOM' => ["\xEF\xBB\xBF{}", 'invalid_encoding', ''],
    'invalid UTF8' => ["{\"a\":\"\xFF\"}", 'invalid_encoding', ''],
    'escaped HTML' => ['{"a":"\\u003cscript>"}', 'invalid_text', '/a'],
]);

it('bounds each JSON resource independently', function (string $limit, int $value, string $json, string $code, string $path) {
    config(['daily_practice.import.'.$limit => $value]);
    packageHasIssue(packageCheck($json), $code, $path);
})->with([
    ['max_bytes', 2, '{"a":1}', 'file_too_large', ''],
    ['max_json_depth', 2, '{"a":{"b":[]}}', 'depth_limit_exceeded', '/a/b'],
    ['max_array_items', 2, '{"a":[1,2,3]}', 'array_limit_exceeded', '/a'],
    ['max_object_properties', 1, '{"a":1,"b":2}', 'object_limit_exceeded', ''],
    ['max_text_length', 2, '{"a":"abc"}', 'string_limit_exceeded', '/a'],
    ['max_id_length', 2, '{"abc":1}', 'property_name_too_long', ''],
]);

it('returns all structural errors without attempting unsafe semantic traversal', function () {
    $data = packageData();
    $data['sessions'][0]['status'] = 'published';
    unset($data['sessions'][0]['tasks'][2]['choices'][0]['transcription_ru']);
    $data['sessions'][1]['tasks'][0] = null;
    $report = packageCheck($data);
    packageHasIssue($report, 'unknown_field', '/sessions/0/status');
    packageHasIssue($report, 'transcription_required', '/sessions/0/tasks/2/choices/0/transcription_ru');
    packageHasIssue($report, 'invalid_type', '/sessions/1/tasks/0');
});

it('detects distinct semantic errors in one report', function () {
    $data = packageData('task-types.json');
    $data['sessions'][0]['unit_id'] = 'absent';
    $data['sessions'][0]['tasks'][5]['accepted_orders'][0] = ['t1', 'absent', 't3'];
    $data['sessions'][0]['tasks'][1]['correct_pairs'][1]['right_id'] = 'r-bonjour';
    $report = packageCheck($data);
    packageHasIssue($report, 'reference_not_found', '/sessions/0/unit_id');
    packageHasIssue($report, 'invalid_word_order', '/sessions/0/tasks/5/accepted_orders/0');
    packageHasIssue($report, 'invalid_matching_pairs', '/sessions/0/tasks/1/correct_pairs');
});

it('checks nested IDs references assessed sets and completed phrases', function (string $kind, string $code, string $path) {
    $data = packageData('task-types.json');
    $tasks = &$data['sessions'][0]['tasks'];
    match ($kind) {
        'choice' => $tasks[0]['choices'][1]['id'] = $tasks[0]['choices'][0]['id'],
        'correct' => $tasks[0]['correct_choice_id'] = 'absent',
        'objective' => $tasks[0]['objective_ids'] = ['absent'],
        'pair_assessed' => $tasks[1]['assessed_vocabulary_ids'] = ['bonjour'],
        'token' => $tasks[5]['tokens'][1]['id'] = 't1',
        'phrase' => $tasks[3]['completed_phrases'][0]['fr'] = 'Autre phrase.',
        'choice_text' => $tasks[0]['choices'][1]['text'] = $tasks[0]['choices'][0]['text'],
    };
    packageHasIssue(packageCheck($data), $code, $path);
})->with([
    ['choice', 'duplicate_id', '/sessions/0/tasks/0/choices/1/id'],
    ['correct', 'reference_not_found', '/sessions/0/tasks/0/correct_choice_id'],
    ['objective', 'reference_not_found', '/sessions/0/tasks/0/objective_ids/0'],
    ['pair_assessed', 'invalid_assessed_vocabulary', '/sessions/0/tasks/1/assessed_vocabulary_ids'],
    ['token', 'duplicate_id', '/sessions/0/tasks/5/tokens/1/id'],
    ['phrase', 'completed_phrase_missing', '/sessions/0/tasks/3/completed_phrases'],
    ['choice_text', 'duplicate_choice_text', '/sessions/0/tasks/0/choices/1/text'],
]);

it('checks pronunciation note coverage and rejects unrelated or repeated notes', function (string $kind, string $code, string $path) {
    $data = packageData('task-types.json');
    $task = &$data['sessions'][0]['tasks'][4];
    match ($kind) {
        'missing' => $task['pronunciation_notes'][0]['fr'] = 'Merci.',
        'unrelated' => $task['pronunciation_notes'][0]['fr'] = 'Bonsoir',
        'duplicate' => $task['pronunciation_notes'][] = $task['pronunciation_notes'][0],
    };
    packageHasIssue(packageCheck($data), $code, $path);
})->with([
    ['missing', 'transcription_required', '/sessions/0/tasks/4/accepted_answers/0'],
    ['unrelated', 'pronunciation_note_mismatch', '/sessions/0/tasks/4/pronunciation_notes/0/fr'],
    ['duplicate', 'duplicate_pronunciation_note', '/sessions/0/tasks/4/pronunciation_notes/1/fr'],
]);

it('resolves existing draft prerequisites and detects cycles crossing the database', function () {
    $data = packageData();
    $first = $data['sessions'][0];
    $second = $data['sessions'][1];
    $second['prerequisite_ids'] = [];
    $row = packageExisting($second, $data['units'][0]);
    $data['sessions'] = [$first];
    $data['sessions'][0]['prerequisite_ids'] = [$second['id']];
    $data['sessions'][0]['public_page']['related_session_ids'] = [];
    expect(packageCheck($data)->toArray()['errors'])->toBe([]);
    LearningSessionVersion::factory()->create([
        'session_id' => $row->id, 'version' => 2,
        'payload' => [...$second, 'version' => 2, 'prerequisite_ids' => [$first['id']]],
    ]);
    packageHasIssue(packageCheck($data), 'prerequisite_cycle', '/sessions/0/prerequisite_ids/0');
});

it('uses existing units and preserves immutable content identity', function () {
    $data = packageData();
    packageExisting($data['sessions'][0], $data['units'][0]);
    unset($data['units']);
    expect(packageCheck($data)->toArray()['errors'])->toBe([]);
    $data['sessions'][0]['level'] = 'A1';
    packageHasIssue(packageCheck($data), 'session_identity_conflict', '/sessions/0/id');
    packageHasIssue(packageCheck($data), 'reference_level_mismatch', '/sessions/0/unit_id');
});

it('accepts identical existing versions regardless of JSON key ordering', function () {
    $data = packageData();
    foreach ($data['sessions'] as $session) {
        packageExisting(array_reverse($session, true), $data['units'][0]);
    }
    expect(packageCheck($data)->toArray()['errors'])->toBe([]);
    $data['sessions'][0]['title'] .= ' Изменено';
    packageHasIssue(packageCheck($data), 'version_conflict', '/sessions/0/version');
    $data['sessions'][0]['version']++;
    expect(packageCheck($data)->toArray()['errors'])->toBe([]);
});

it('checks dictionary and media references without downloading anything', function () {
    $data = packageData('task-types.json');
    $data['sessions'][0]['vocabulary'][0]['dictionary_word_id'] = 987654321;
    $data['sessions'][0]['tasks'][0]['prompt']['audio']['asset_id'] = '11111111-1111-4111-8111-111111111111';
    $report = packageCheck($data);
    packageHasIssue($report, 'reference_not_found', '/sessions/0/vocabulary/0/dictionary_word_id');
    packageHasIssue($report, 'reference_not_found', '/sessions/0/tasks/0/prompt/audio/asset_id');
    unset($data['sessions'][0]['vocabulary'][0]['dictionary_word_id']);
    $media = LearningMedia::create([
        'disk' => 'local', 'path' => 'learning/fixture.mp3', 'mime_type' => 'audio/mpeg',
        'size' => 100, 'checksum' => hash('sha256', 'fixture'), 'review_status' => 'approved',
    ]);
    $data['sessions'][0]['tasks'][0]['prompt']['audio']['asset_id'] = $media->id;
    expect(packageCheck($data)->toArray()['errors'])->toBe([]);
    $media->update(['mime_type' => 'image/png']);
    packageHasIssue(packageCheck($data), 'invalid_media_type', '/sessions/0/tasks/0/prompt/audio/asset_id');
});

it('allows browser TTS for checkpoints while keeping explicit publication review', function () {
    $data = packageData('task-types.json');
    $data['sessions'][0]['kind'] = 'checkpoint';
    $report = packageCheck($data);
    expect($report->toArray()['errors'])->toBe([]);
    $warnings = collect($report->warnings())->keyBy('code');
    expect($warnings['browser_tts']->blocksPublication)->toBeFalse()->and($warnings)->not->toHaveKey('checkpoint_audio_required');
});

it('never writes to application tables while validating valid or invalid content', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    packageCheck(packageFixture());
    packageCheck(packageFixture('invalid/prerequisite-cycle.json'));
    expect(array_values(array_filter($queries, fn (string $sql): bool => preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)/i', $sql) === 1)))->toBe([])
        ->and(LearningSession::count())->toBe(0)
        ->and(LearningSessionVersion::count())->toBe(0);
});

it('limits error output explicitly and does not retain state across calls', function () {
    config(['daily_practice.import.max_validation_errors' => 2]);
    $validator = app(SessionPackageValidator::class);
    $report = $validator->validate('{"x":1,"y":2,"z":3}');
    expect($report->toArray()['truncated'])->toBeTrue();
    packageHasIssue($report, 'error_limit_exceeded', '');
    expect($validator->validate(packageFixture())->toArray()['errors'])->toBe([]);
});

it('reports unreadable files and applies byte limits when reading uploads', function () {
    packageHasIssue(app(SessionPackageValidator::class)->validateFile('/tmp/nonexistent-learning-package.json'), 'upload_unreadable', '/file');
    $file = UploadedFile::fake()->createWithContent('package.json', packageFixture());
    expect(app(SessionPackageValidator::class)->validateFile($file->getRealPath())->isValid())->toBeTrue();
    config(['daily_practice.import.max_bytes' => 8]);
    packageHasIssue(app(SessionPackageValidator::class)->validateFile($file->getRealPath()), 'file_too_large', '/file');
});

it('validates uploads only for verified administrators and returns actionable JSON', function () {
    Route::post('/_tests/package-upload', function (UploadSessionPackageRequest $request) {
        return response()->json($request->packageReport()->toArray());
    })->middleware('auth');
    $user = User::factory()->create();
    $this->actingAs($user)->post('/_tests/package-upload', ['file' => UploadedFile::fake()->createWithContent('package.json', '{}')])->assertForbidden();
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $admin = User::factory()->create(['rang_id' => $rank->id]);
    $this->actingAs($admin)->post('/_tests/package-upload', ['file' => UploadedFile::fake()->createWithContent('package.json', packageFixture())])->assertOk()->assertJsonPath('valid', true);
    $this->post('/_tests/package-upload', ['file' => UploadedFile::fake()->createWithContent('package.json', packageFixture('invalid/missing-transcription.json'))])
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')
        ->assertJsonFragment(['path' => '/sessions/0/tasks/2/choices/0/transcription_ru', 'code' => 'transcription_required']);
    $this->post('/_tests/package-upload')->assertUnprocessable()->assertJsonPath('errors.0.path', '/file');
    $admin->update(['email_verified_at' => null]);
    $this->post('/_tests/package-upload')->assertForbidden();
});

it('fails closed if the authoritative schema introduces an unsupported keyword', function () {
    $file = UploadedFile::fake()->createWithContent('schema.json', '{"type":"object","unevaluatedProperties":false}');
    config(['daily_practice.schema_path' => $file->getRealPath()]);
    expect(fn () => new SessionPackageSchemaValidator)->toThrow(LogicException::class, 'Unsupported package schema keyword');
});

it('matches an independent Draft 2020-12 validator across schema mutations', function () {
    $autoload = getenv('PRACTICE_SCHEMA_AUTOLOAD');
    if (! class_exists(\Opis\JsonSchema\CompliantValidator::class) && $autoload && is_file($autoload)) {
        $loader = require $autoload;
        $loader->unregister();
        $loader->register(false);
    }
    if (! class_exists(\Opis\JsonSchema\CompliantValidator::class)) {
        $this->markTestSkipped('Set PRACTICE_SCHEMA_AUTOLOAD for the independent schema oracle.');
    }
    $schema = json_decode(file_get_contents(config('daily_practice.schema_path')), false, 512, JSON_THROW_ON_ERROR);
    $oracle = new \Opis\JsonSchema\CompliantValidator;
    $validator = new SessionPackageSchemaValidator;
    $data = json_decode(packageFixture('task-types.json'));
    $cases = [$data];
    $collect = function (mixed $value, array $path = []) use (&$collect): array {
        $paths = [$path];
        if (is_array($value) || is_object($value)) {
            foreach ($value as $key => $child) {
                $paths = [...$paths, ...$collect($child, [...$path, $key])];
            }
        }

        return $paths;
    };
    $paths = $collect($data);
    foreach ($paths as $path) {
        if ($path === []) {
            continue;
        }
        foreach (['remove', 'null', 'wrong_type'] as $mutation) {
            $copy = unserialize(serialize($data));
            $parent = &$copy;
            $leaf = array_pop($path);
            foreach ($path as $segment) {
                if (is_object($parent)) {
                    $parent = &$parent->{$segment};
                } else {
                    $parent = &$parent[$segment];
                }
            }
            if (is_object($parent)) {
                if ($mutation === 'remove') {
                    unset($parent->{$leaf});
                } else {
                    $parent->{$leaf} = $mutation === 'null' ? null : (object) ['unexpected' => true];
                }
            } else {
                if ($mutation === 'remove') {
                    unset($parent[$leaf]);
                    $parent = array_values($parent);
                } else {
                    $parent[$leaf] = $mutation === 'null' ? null : (object) ['unexpected' => true];
                }
            }
            $cases[] = $copy;
            $path[] = $leaf;
            unset($parent);
        }
    }
    foreach (["\n", ' ', '<b>'] as $suffix) {
        $copy = unserialize(serialize($data));
        $copy->sessions[0]->id .= $suffix;
        $cases[] = $copy;
    }
    foreach ([0, 1, 1.0, -1, 1.5, 4294967295, 4294967296, PHP_INT_MAX] as $number) {
        $copy = unserialize(serialize($data));
        $copy->sessions[0]->version = $number;
        $cases[] = $copy;
    }
    foreach ($cases as $index => $instance) {
        expect($validator->validate($instance) === [], 'Schema parity case '.$index)
            ->toBe($oracle->validate($instance, $schema)->isValid());
    }
    expect(count($cases))->toBeGreaterThan(1000);
});

it('keeps Docker runtime schema and fixtures identical to the authored contract', function () {
    $root = getenv('PRACTICE_CONTRACT_ROOT');
    if (! $root || ! is_dir($root)) {
        $this->markTestSkipped('Set PRACTICE_CONTRACT_ROOT to verify the authoring mirror.');
    }
    expect(file_get_contents(config('daily_practice.schema_path')))->toBe(file_get_contents($root.'/schema/session-package-v1.schema.json'));
    foreach (['pre-a1-u01-examples.json', 'task-types.json'] as $file) {
        expect(packageFixture($file))->toBe(file_get_contents($root.'/examples/'.$file));
    }
    foreach (glob(base_path('tests/Fixtures/Learning/invalid/*.json')) as $file) {
        expect(file_get_contents($file))->toBe(file_get_contents($root.'/examples/invalid/'.basename($file)));
    }
});

it('requires a complete phrase for every accepted word order', function () {
    $data = packageData('task-types.json');
    $data['sessions'][0]['tasks'][5]['completed_phrases'][0]['fr'] = 'Autre phrase.';
    packageHasIssue(packageCheck($data), 'completed_phrase_missing', '/sessions/0/tasks/5/completed_phrases');
});

it('resolves objectives and checkpoints from the database and enforces their levels and kinds', function () {
    $data = packageData();
    $checkpoint = $data['sessions'][1];
    $checkpoint['kind'] = 'checkpoint';
    $checkpoint['prerequisite_ids'] = [];
    $row = packageExisting($checkpoint, $data['units'][0]);
    $objective = \App\Models\Learning\LearningObjective::create([
        'stable_id' => 'PRE_A1.EXTERNAL', 'level' => 'PRE_A1', 'title' => 'Внешняя цель',
    ]);
    $data['sessions'] = [$data['sessions'][0]];
    $data['curricula'] = [[
        'id' => 'PRE_A1-PROGRAM', 'version' => 1, 'level' => 'PRE_A1',
        'required_objective_ids' => [$objective->stable_id],
        'checkpoint_session_ids' => [$checkpoint['id']],
    ]];
    expect(packageCheck($data)->toArray()['errors'])->toBe([]);
    $data['curricula'][0]['required_objective_ids'][] = 'MISSING';
    packageHasIssue(packageCheck($data), 'reference_not_found', '/curricula/0/required_objective_ids/1');
    array_pop($data['curricula'][0]['required_objective_ids']);
    $foreign = \App\Models\Learning\LearningObjective::create(['stable_id' => 'A1.FOREIGN.OBJECTIVE', 'level' => 'A1', 'title' => 'Цель другого уровня']);
    $data['curricula'][0]['required_objective_ids'][0] = $foreign->stable_id;
    packageHasIssue(packageCheck($data), 'reference_level_mismatch', '/curricula/0/required_objective_ids/0');
    $data['curricula'][0]['required_objective_ids'][0] = $objective->stable_id;
    LearningSessionVersion::factory()->create([
        'session_id' => $row->id, 'version' => 2,
        'payload' => [...$checkpoint, 'kind' => 'practice', 'version' => 2],
    ]);
    packageHasIssue(packageCheck($data), 'invalid_checkpoint', '/curricula/0/checkpoint_session_ids/0');
});

it('rejects missing and wrong-level dependencies inside the existing graph', function (string $target, string $code) {
    $data = packageData();
    $first = $data['sessions'][0];
    $second = $data['sessions'][1];
    $second['prerequisite_ids'] = [$target];
    packageExisting($second, $data['units'][0]);
    if ($target !== 'MISSING') {
        $external = [...$first, 'id' => $target, 'unit_id' => 'A1-U01', 'level' => 'A1', 'prerequisite_ids' => []];
        packageExisting($external);
    }
    $data['sessions'] = [$first];
    $data['sessions'][0]['prerequisite_ids'] = [$second['id']];
    packageHasIssue(packageCheck($data), $code, '/sessions/0/prerequisite_ids/0');
})->with([
    ['MISSING', 'reference_not_found'],
    ['A1-EXTERNAL', 'reference_level_mismatch'],
]);

it('bounds the database graph traversal', function () {
    config(['daily_practice.import.max_context_nodes' => 1]);
    packageHasIssue(packageCheck(packageFixture()), 'context_limit_exceeded', '');
});

it('preserves duplicate-key error context after parsing the rest of the package', function () {
    $json = str_replace('"id": "Q01"', '"id": "Q01", "id": "Q01"', packageFixture());
    $report = packageCheck($json);
    packageHasIssue($report, 'duplicate_json_key', '/sessions/0/tasks/0/id');
    $issue = collect($report->errors())->first(fn ($issue): bool => $issue->path === '/sessions/0/tasks/0/id');
    expect($issue->sessionId)->toBe(packageData()['sessions'][0]['id'])
        ->and($issue->taskId)->toBe('Q01');
});
