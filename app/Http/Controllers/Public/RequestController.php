<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\PublicEventRequest;
use App\Models\EventRequest;
use App\Models\Service;
use App\Services\Booking\RequestIntake;
use App\Services\Documents\UploadRules;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public Event Production Request. Loads only public data: offered services
 * and option lists. Never inventory, staff or pricing (D18).
 */
class RequestController extends Controller
{
    public function create(Lookups $lookups): View
    {
        return view('public.request.form', [
            'services' => Service::query()->offered()->get(['id', 'name', 'icon', 'description']),
            'eventTypes' => $lookups->options('event_type'),
            'budgets' => $lookups->options('budget_range'),
            'submissionKey' => old('submission_key', (string) Str::uuid()),
            'formStarted' => Crypt::encryptString((string) now()->timestamp),
            'accept' => UploadRules::accept(),
            'fileHint' => UploadRules::describe(),
        ]);
    }

    public function store(PublicEventRequest $request, RequestIntake $intake): RedirectResponse
    {
        $eventRequest = $intake->submit(
            $request->safe()->except(['files', 'website', 'form_started']),
            $request->file('files', []),
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
        );

        return redirect()->route('requests.received', $eventRequest->public_token);
    }

    public function received(string $token): View
    {
        return view('public.request.received', ['request' => $this->find($token)]);
    }

    private function find(string $token): EventRequest
    {
        abort_unless(Str::isUlid($token), 404);

        return EventRequest::where('public_token', $token)->firstOrFail();
    }
}
