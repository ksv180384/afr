<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '/api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\PracticeOperations::class);
        $middleware->trimStrings(except: [fn (Request $request) => $request->is('daily/api/guest-sync', 'daily/api/reports')]);
        $middleware->convertEmptyStringsToNull(except: [fn (Request $request) => $request->is('daily/api/guest-sync', 'daily/api/reports')]);
        $middleware->web(append: [
            //            \App\Http\Middleware\PrerenderIfCrawler::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\PracticeRobots::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\TrackRefererMiddleware::class,
        ]);

        $middleware->alias([
            'is-admin' => \App\Http\Middleware\AdminMiddleware::class,
            'is-user-ban' => \App\Http\Middleware\CheckBannedUserMiddleware::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $error) {
            if (request()->is('daily/api/*', 'admin/daily-practice', 'admin/daily-practice/*')) {
                $expected = $error instanceof \App\Services\Learning\AnswerCheckingException || $error instanceof \App\Services\Learning\ContentOperationException;
                app(\App\Services\Learning\PracticeTelemetry::class)->record($expected ? 'practice_rejection' : 'practice_failure', ['status' => $expected ? $error->errorCode->httpStatus() : 500,
                    'error_code' => $expected ? $error->errorCode->value : null,
                    'error_class' => $error::class, 'error_file' => basename($error->getFile()), 'error_line' => $error->getLine()]);

                return false;
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception) => $request->is('daily/api', 'daily/api/*') || $request->expectsJson());
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $exception, Request $request) {
            if (($request->is('admin/daily-practice', 'admin/daily-practice/*') && $request->expectsJson()) || $request->is('daily/api', 'daily/api/*')) {
                return response()->json(['code' => 'unauthenticated', 'message' => 'Войдите в аккаунт.'], 401);
            }

            return null;
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('daily/api', 'daily/api/*')) {
                $status = $response->getStatusCode();
                if ($status >= 400 && (! $response instanceof \Illuminate\Http\JsonResponse || ! is_string($response->getData(true)['code'] ?? null))) {
                    $code = match ($status) {
                        401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 409 => 'revision_conflict', 419 => 'session_expired', 422 => 'validation_failed', 429 => 'rate_limited', default => 'unavailable'
                    };
                    $message = match ($status) {
                        401 => 'Войдите в тот же аккаунт, чтобы отправить очередь.', 419 => 'Сессия истекла. Восстановите вход; очередь сохранена на устройстве.', 404 => 'Данные не найдены.', 429 => 'Слишком много запросов. Повторите позже.', default => 'Не удалось выполнить запрос. Повторите позже.'
                    };
                    $retryHeaders = array_intersect_key($response->headers->all(), array_flip(['retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset']));
                    $response = response()->json(['code' => $code, 'message' => $message], $status >= 500 ? 503 : $status, $retryHeaders);
                }
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

                return $response;
            }
            if (! $request->is('admin/daily-practice', 'admin/daily-practice/*') && ! app()->environment(['testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                return Inertia::render('ErrorPage', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            } elseif ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('sitemap:generate')->daily();
    })
    ->create();
