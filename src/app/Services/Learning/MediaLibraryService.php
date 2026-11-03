<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningMedia;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MediaLibraryService
{
    private function invalid(): never
    {
        throw ValidationException::withMessages(['file' => 'Нужен целый PCM WAV: 16 бит, 1–2 канала, 8–96 кГц, до 10 МБ и 5 минут.']);
    }

    public function inspect(string $bytes): int
    {
        $size = strlen($bytes);
        if ($size < 44 || $size > 10 * 1024 * 1024 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WAVE' || unpack('V', substr($bytes, 4, 4))[1] !== $size - 8) {
            $this->invalid();
        }
        $format = null;
        $dataSize = null;
        $offset = 12;
        while ($offset < $size) {
            if ($offset + 8 > $size) {
                $this->invalid();
            }
            $name = substr($bytes, $offset, 4);
            $length = unpack('V', substr($bytes, $offset + 4, 4))[1];
            $offset += 8;
            if ($length > $size - $offset) {
                $this->invalid();
            }
            if ($name === 'fmt ') {
                if ($format !== null || $length < 16) {
                    $this->invalid();
                }
                $format = unpack('vcodec/vchannels/Vrate/Vbytes/vblock/vbits', substr($bytes, $offset, 16));
            }
            if ($name === 'data') {
                if ($dataSize !== null) {
                    $this->invalid();
                }
                $dataSize = $length;
            }
            $offset += $length + ($length % 2);
        }
        if ($offset !== $size || ! $format || ! $dataSize || $format['codec'] !== 1 || ! in_array($format['channels'], [1, 2], true)
            || $format['bits'] !== 16 || $format['rate'] < 8000 || $format['rate'] > 96000
            || $format['block'] !== $format['channels'] * 2 || $format['bytes'] !== $format['rate'] * $format['block']
            || $dataSize % $format['block'] !== 0) {
            $this->invalid();
        }
        $duration = (int) ceil($dataSize * 1000 / $format['bytes']);
        if ($duration < 1 || $duration > 300000) {
            $this->invalid();
        }

        return $duration;
    }

    public function upload(User $actor, UploadedFile $file): LearningMedia
    {
        app(ContentAccess::class)->check($actor);
        $bytes = file_get_contents($file->getRealPath());
        if (! in_array($file->getMimeType(), ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'], true)) {
            $this->invalid();
        }
        $duration = $this->inspect($bytes);
        $id = (string) Str::uuid();
        $path = 'media/'.$id.'.wav';
        if (! Storage::disk('learning_private')->put($path, $bytes)) {
            throw new ContentOperationException(\App\Enums\Learning\PracticeErrorCode::Unavailable, 'Не удалось сохранить аудиофайл. Повторите загрузку.');
        }
        try {
            return DB::transaction(function () use ($actor, $bytes, $duration, $path) {
                $media = LearningMedia::create(['disk' => 'learning_private', 'path' => $path, 'mime_type' => 'audio/wav',
                    'size' => strlen($bytes), 'duration_ms' => $duration, 'checksum' => hash('sha256', $bytes), 'created_by' => $actor->id, 'review_status' => 'pending']);
                app(ContentAccess::class)->audit($actor, 'media_uploaded', [], [], ['media_id' => $media->id]);

                return $media;
            });
        } catch (\Throwable $error) {
            Storage::disk('learning_private')->delete($path);
            throw $error;
        }
    }

    public function file(LearningMedia $media): string
    {
        abort_unless(str_starts_with($media->mime_type, 'audio/') && $media->disk === 'learning_private' && preg_match('~^[a-zA-Z0-9/_-]+\.(wav|mp3|ogg|m4a)$~D', $media->path), 404);
        $disk = Storage::disk('learning_private');
        abort_unless($disk->exists($media->path) && hash_equals($media->checksum, hash_file('sha256', $disk->path($media->path))), 404);

        return $disk->path($media->path);
    }

    public function approve(User $actor, string $id): LearningMedia
    {
        app(ContentAccess::class)->check($actor);

        return DB::transaction(function () use ($actor, $id) {
            $media = LearningMedia::query()->lockForUpdate()->findOrFail($id);
            abort_if($media->archived_at !== null, 409, 'Архивный файл нельзя одобрить.');
            $this->file($media);
            $media->update(['review_status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now('UTC')]);
            app(ContentAccess::class)->audit($actor, 'media_approved', [], [], ['media_id' => $id]);

            return $media;
        });
    }
}
