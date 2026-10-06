<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only: there are deliberately no routes that change audit entries.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $tz = config('nebo.display_timezone');

        $logs = AuditLog::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%')
                ->orWhere('auditable_id', $term)))
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($filters['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', now($tz)->parse($d, $tz)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', now($tz)->parse($d, $tz)->endOfDay()->utc()))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('internal.audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'types' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('internal.audit.show', ['log' => $auditLog]);
    }
}
