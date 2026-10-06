<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records who did what. Recording never breaks the action being recorded.
 */
class Audit
{
    /** Attributes that are never written to the audit log. */
    public const REDACTED = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(string $event, string $description, ?Model $subject = null, ?array $old = null, ?array $new = null): void
    {
        try {
            $user = auth()->user();
            $console = app()->runningInConsole() && ! app()->runningUnitTests();
            $request = $console ? null : request();

            AuditLog::create([
                'user_id' => $user?->getKey(),
                'user_name' => $user?->name ?? ($console ? 'Console' : 'System'),
                'event' => $event,
                'auditable_type' => $subject ? class_basename($subject) : null,
                'auditable_id' => $subject ? (string) $subject->getKey() : null,
                'description' => Str::limit($description, 490),
                'old_values' => $old ? self::clean($old) : null,
                'new_values' => $new ? self::clean($new) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
                'url' => $request ? Str::limit($request->fullUrl(), 495, '') : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::REDACTED, true)) {
                $values[$key] = '[redacted]';
            } elseif ($value instanceof \BackedEnum) {
                $values[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format(DATE_ATOM);
            }
        }

        return $values;
    }
}
