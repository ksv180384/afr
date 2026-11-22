<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\PracticeReportRequest;
use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningSession;
use App\Services\Learning\AccountRunService;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class PracticeReportController extends Controller
{
    public function __invoke(PracticeReportRequest $request, AccountRunService $runs)
    {
        $input = $request->validated();
        $lessons = LearningSession::query()->with('unit', 'levelDefinition');
        if (! empty($input['run_id'])) {
            abort_unless($request->user(), 401);
            $run = $runs->owned($request->user(), $input['run_id']);
            $lessons->whereKey($run->session_id);
        }
        $session = isset($input['lesson_id']) ? $lessons->findOrFail($input['lesson_id'])
            : ((clone $lessons)->where('legacy_content_id', $input['session_version_id'])->first()
                ?? $lessons->findOrFail($input['session_version_id']));
        if (empty($input['run_id']) && ! ($request->user() && $session->vocabularySources()->where('user_id', $request->user()->id)->exists())) {
            abort_unless($session->published_at && $session->status->value === 'published'
                && $session->archived_at === null && $session->unit->published_at && ! $session->unit->archived_at
                && $session->levelDefinition->isPublished(), 404);
        }
        if (! empty($input['task_id']) && ! collect($session->payload['tasks'] ?? [])->contains('id', $input['task_id'])) {
            throw ValidationException::withMessages(['task_id' => 'Шаг отсутствует в указанном занятии.']);
        }
        if ($request->boolean('include_answer') && empty($input['task_id'])) {
            throw ValidationException::withMessages(['answer' => 'Укажите шаг, к которому относится ответ.']);
        }
        $hash = CanonicalJson::hash(['user_id' => $request->user()?->id, 'input' => $input]);
        $previous = LearningContentReport::where('client_report_id', $input['client_report_id'])->first();
        if ($previous) {
            abort_unless(hash_equals($previous->payload_hash, $hash), 409, 'Этот ID сообщения уже использован.');

            return response()->json(['report_id' => $previous->id, 'status' => 'received']);
        }
        try {
            $report = LearningContentReport::create(['client_report_id' => $input['client_report_id'], 'payload_hash' => $hash,
                'session_id' => $session->id, 'task_id' => $input['task_id'] ?? null, 'user_id' => $request->user()?->id,
                'text' => $input['text'], 'answer_snapshot' => $request->boolean('include_answer') ? ($input['answer'] ?? null) : null, 'status' => 'open']);
        } catch (QueryException $error) {
            $report = LearningContentReport::where('client_report_id', $input['client_report_id'])->first();
            if (! $report) {
                throw $error;
            }
            abort_unless(hash_equals($report->payload_hash, $hash), 409);
        }

        return response()->json(['report_id' => $report->id, 'status' => 'received']);
    }
}
