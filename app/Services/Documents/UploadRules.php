<?php

namespace App\Services\Documents;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * What files may be uploaded. Checked by extension AND by content: the MIME
 * type sniffed from the bytes with finfo (or, for CAD drawings, the file signature)
 * must match the extension. Executable and script formats are never allowed.
 */
class UploadRules
{
    public const MAX_KB = 10240; // 10 MB per file

    public const MAX_FILES = 5;

    /** extension => allowed sniffed MIME types */
    public const TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'dwg' => [], // checked by signature
        'dxf' => [], // checked by signature
    ];

    public static function accept(): string
    {
        return implode(',', array_map(fn ($e) => '.'.$e, array_keys(self::TYPES)));
    }

    public static function describe(): string
    {
        return 'PDF, images (JPG, PNG, WebP), Word, Excel, PowerPoint or CAD (DWG, DXF), up to 10 MB each';
    }

    /**
     * Validation rule for one uploaded file.
     *
     * @return list<mixed>
     */
    public static function rule(): array
    {
        return ['file', 'max:'.self::MAX_KB, function (string $attribute, mixed $file, Closure $fail) {
            if (! $file instanceof UploadedFile || ! self::passes($file)) {
                $fail('This file type isn\'t accepted. Upload '.self::describe().'.');
            }
        }];
    }

    public static function passes(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! array_key_exists($extension, self::TYPES)) {
            return false;
        }

        if ($extension === 'dwg') {
            return (bool) preg_match('/^AC10\d\d/', self::head($file, 6));
        }

        if ($extension === 'dxf') {
            $head = ltrim(self::head($file, 64));

            return str_starts_with($head, '0') && str_contains($head, 'SECTION') || str_starts_with(self::head($file, 22), 'AutoCAD Binary DXF');
        }

        return in_array(self::sniff($file), self::TYPES[$extension], true);
    }

    /** The MIME type read from the file's bytes (never the client's claim or the name). */
    public static function sniff(UploadedFile $file): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
    }

    private static function head(UploadedFile $file, int $bytes): string
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $head = (string) fread($handle, $bytes);
        fclose($handle);

        return $head;
    }
}
