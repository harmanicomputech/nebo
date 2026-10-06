<?php

namespace App\Support;

/**
 * The browser installer for hosts without a terminal (D69). It is active only
 * when NEBO_INSTALLER is true (the upload package's first boot writes that)
 * and the lock file does not exist yet.
 */
class Installer
{
    public const PHP_MIN = '8.3.0';

    public const EXTENSIONS = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'];

    /** Overridable in tests. */
    public static ?string $lockFile = null;

    public static function lockPath(): string
    {
        return self::$lockFile ?? storage_path('app/installed.lock');
    }

    public static function installed(): bool
    {
        return is_file(self::lockPath());
    }

    public static function active(): bool
    {
        return (bool) config('nebo.installer') && ! self::installed();
    }

    public static function lock(): void
    {
        file_put_contents(self::lockPath(), 'Installed '.now()->toIso8601String().PHP_EOL);
    }

    /**
     * Server requirements: [label, passed, hint].
     *
     * @return list<array{0: string, 1: bool, 2: string}>
     */
    public static function requirements(): array
    {
        $checks = [['PHP '.self::PHP_MIN.' or newer', version_compare(PHP_VERSION, self::PHP_MIN, '>='), 'This server runs PHP '.PHP_VERSION.'. Choose PHP 8.3 or 8.4 for the domain in DirectAdmin (Domain Setup → PHP version).']];
        foreach (self::EXTENSIONS as $ext) {
            $checks[] = ["PHP extension: {$ext}", extension_loaded($ext), 'Enable it in DirectAdmin (PHP extensions / Select PHP version).'];
        }
        foreach (['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $checks[] = ["Writable folder: {$dir}", is_dir(base_path($dir)) && is_writable(base_path($dir)), 'Set the folder permission to 755 (or 775) in the File Manager.'];
        }
        $env = app()->environmentFilePath();
        $checks[] = ['Writable settings file: .env', is_file($env) ? is_writable($env) : is_writable(base_path()), 'Set the .env file permission to 644 (or 664).'];

        return $checks;
    }

    public static function ready(): bool
    {
        return collect(self::requirements())->every(fn (array $check) => $check[1]);
    }

    /** Sets keys in the .env file, keeping every other line. */
    public static function writeEnv(array $values, ?string $path = null): void
    {
        $path ??= app()->environmentFilePath();
        $lines = is_file($path) ? preg_split('/\R/', (string) file_get_contents($path)) : [];

        foreach ($values as $key => $value) {
            $line = $key.'='.self::envValue($value);
            $found = false;
            foreach ($lines as $i => $existing) {
                if (preg_match('/^#?\s*'.preg_quote($key, '/').'=/', $existing)) {
                    $lines[$i] = $line;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $lines[] = $line;
            }
        }

        file_put_contents($path, rtrim(implode(PHP_EOL, $lines)).PHP_EOL, LOCK_EX);
    }

    private static function envValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $value = (string) $value;
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-]+$/', $value)) {
            return $value;
        }

        // Quoted: escape backslashes, quotes and $ (the .env parser expands ${VAR}).
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
