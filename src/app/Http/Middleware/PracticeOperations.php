<?php

namespace App\Http\Middleware;

use App\Services\Learning\AnswerCheckingException;
use App\Services\Learning\ContentOperationException;
use App\Services\Learning\PracticeTelemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PracticeOperations
{
    public function __construct(private readonly PracticeTelemetry $telemetry) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('daily/api/*', 'admin/daily-practice', 'admin/daily-practice/*')) {
            return $next($request);
        }
        $start = hrtime(true);
        $status = 500;
        $bytes = 0;
        $rejected = 0;
        $code = null;
        try {
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                $limit = match (true) {
                    $request->is('daily/api/guest-sync') => config('daily_practice.operations.sync_bytes'),
                    $request->is('admin/daily-practice/imports/preview', 'admin/daily-practice/media') => config('daily_practice.import.max_bytes') + 65536,
                    default => config('daily_practice.operations.request_bytes'),
                };
                if ((int) $request->header('Content-Length', 0) > $limit || strlen($request->getContent()) > $limit) {
                    $status = 413;

                    return response()->json(['code' => 'payload_too_large', 'message' => 'Запрос слишком большой. Разделите пакет и повторите отправку.'], 413)
                        ->withHeaders(['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
                }
            }
            $response = $next($request);
            $status = $response->getStatusCode();
            $bytes = strlen($response->getContent() ?: '');
            if ($request->isMethod('GET') && $request->is('daily/api/*') && $response instanceof \Illuminate\Http\JsonResponse && $bytes > config('daily_practice.operations.response_bytes')) {
                $status = 503;

                return response()->json(['code' => 'response_too_large', 'message' => 'Результат слишком большой. Уточните фильтры или выберите меньший набор.'], 503)
                    ->withHeaders(['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
            }
            if ($response instanceof \Illuminate\Http\JsonResponse && ! $request->isMethod('GET')) {
                $body = $response->getData(true);
                $rejected = count($body['rejected'] ?? []);
                $code = is_string($body['code'] ?? null) ? $body['code'] : null;
            }
            if ($request->is('admin/daily-practice', 'admin/daily-practice/*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }

            return $response;
        } catch (\Throwable $error) {
            $status = match (true) {
                $error instanceof AnswerCheckingException, $error instanceof ContentOperationException => $error->errorCode->httpStatus(),
                $error instanceof \Illuminate\Http\Exceptions\HttpResponseException => $error->getResponse()->getStatusCode(),
                $error instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
                $error instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                $error instanceof HttpExceptionInterface => $error->getStatusCode(),
                $error instanceof \Illuminate\Validation\ValidationException => 422,
                $error instanceof \Illuminate\Auth\AuthenticationException => 401,
                default => 500,
            };
            $code = ($error instanceof AnswerCheckingException || $error instanceof ContentOperationException) ? $error->errorCode->value : null;
            throw $error;
        } finally {
            if (! $request->isMethod('GET') || $status >= 400) {
                $this->telemetry->record('practice_request', ['route' => $request->route()?->getName(), 'method' => $request->method(),
                    'status' => $status, 'duration_ms' => (int) ((hrtime(true) - $start) / 1000000), 'response_bytes' => $bytes,
                    'rejected_count' => $rejected, 'error_code' => $code]);
            }
        }
    }
}
