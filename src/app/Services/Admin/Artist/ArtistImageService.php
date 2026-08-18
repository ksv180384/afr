<?php

namespace App\Services\Admin\Artist;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ArtistImageService
{
    public const SIZE = 312;

    public function store(UploadedFile $image, string $directory): string
    {
        $encodedImage = (new ImageManager(new Driver()))
            ->read($image->getPathname())
            ->cover(self::SIZE, self::SIZE)
            ->toWebp(quality: 85);

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';

        Storage::disk('public')->put($path, (string) $encodedImage);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
