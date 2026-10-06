<?php

namespace App\Http\Controllers\Internal\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Events\ProductionCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request, ProductionCalendar $calendar): View
    {
        $this->authorize('viewAny', Event::class);
        $data = $request->validate(['view' => ['nullable', 'in:month,week,day'], 'date' => ['nullable', 'date']]);
        $tz = config('nebo.display_timezone');
        $view = $data['view'] ?? 'month';
        $date = isset($data['date']) ? CarbonImmutable::parse($data['date'], $tz) : CarbonImmutable::now($tz);

        [$from, $to, $prev, $next, $title] = match ($view) {
            'day' => [$date->startOfDay(), $date->startOfDay()->addDay(), $date->subDay(), $date->addDay(), $date->format('l, j F Y')],
            'week' => [$date->startOfWeek(), $date->startOfWeek()->addWeek(), $date->subWeek(), $date->addWeek(), 'Week of '.$date->startOfWeek()->format('j M Y')],
            default => [$date->startOfMonth()->startOfWeek(), $date->endOfMonth()->endOfWeek()->startOfDay()->addDay(), $date->subMonthNoOverflow(), $date->addMonthNoOverflow(), $date->format('F Y')],
        };

        return view('internal.events.calendar', $calendar->build($request->user(), $from, $to) + [
            'view' => $view,
            'date' => $date,
            'month' => $date->month,
            'title' => $title,
            'prev' => $prev->toDateString(),
            'next' => $next->toDateString(),
            'today' => CarbonImmutable::now($tz),
        ]);
    }
}
