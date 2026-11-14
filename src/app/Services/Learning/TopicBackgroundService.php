<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;

final class TopicBackgroundService
{
    public const MAX_KILOBYTES = 10240;

    public function __construct(private readonly ContentTransaction $transaction) {}

    public function replace(User $actor, int $unit, UploadedFile $file): LearningUnit
    {
        try {
            $image = (new ImageManager(new Driver(), decodeAnimation: false, strip: true))
                ->read($file->getPathname())
                ->scaleDown(width: 1920, height: 2400)
                ->toWebp(quality: 85);
        } catch (DecoderException) {
            throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Не удалось прочитать изображение.');
        }

        $disk = Storage::disk('public');
        $path = 'practice/topic-backgrounds/'.Str::uuid().'.webp';
        if (! $disk->put($path, (string) $image)) {
            throw new ContentOperationException(PracticeErrorCode::Unavailable, 'Не удалось сохранить фон темы.');
        }

        try {
            [$topic, $previousPath] = $this->transaction->run($actor, function () use ($unit, $path): array {
                $topic = LearningUnit::query()->findOrFail($unit);
                $previousPath = $topic->background_path;
                $topic->update(['background_path' => $path]);

                return [$topic, $previousPath];
            });
        } catch (\Throwable $error) {
            $disk->delete($path);

            throw $error;
        }

        if ($previousPath) {
            $disk->delete($previousPath);
        }

        return $topic;
    }
}
