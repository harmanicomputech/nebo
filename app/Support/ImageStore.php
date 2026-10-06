<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploaded images privately (D15). Every image is decoded and
 * re-encoded with GD: that strips EXIF (including GPS), drops anything that
 * isn't really an image, and caps the size at MAX_EDGE pixels.
 */
class ImageStore
{
    public const MAX_EDGE = 1600;

    public const DISK = 'local';

    public function store(UploadedFile $file, string $folder): string
    {
        $source = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };

        if (! $source) {
            throw ValidationException::withMessages(['image' => 'The image could not be read. Upload a JPG, PNG or WebP file.']);
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        $webp = function_exists('imagewebp');
        ob_start();
        $webp ? imagewebp($target, null, 82) : imagejpeg($target, null, 85);
        $bytes = (string) ob_get_clean();

        $path = 'images/'.$folder.'/'.Str::random(40).($webp ? '.webp' : '.jpg');
        Storage::disk(self::DISK)->put($path, $bytes);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
