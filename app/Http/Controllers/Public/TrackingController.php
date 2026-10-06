<?php

namespace App\Http\Controllers\Public;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\EventRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Customers follow their request with the private link from their
 * confirmation, or by reference + email. They see a simple stage, never
 * internal notes, staff names or statuses.
 */
class TrackingController extends Controller
{
    public function form(): View
    {
        return view('public.request.track-form');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $found = EventRequest::where('reference', strtoupper(trim($data['reference'])))
            ->where('email', mb_strtolower(trim($data['email'])))->first();

        return $found
            ? redirect()->route('requests.track', $found->public_token)
            : back()->withInput()->withErrors(['reference' => 'We couldn\'t find a request with that reference and email.']);
    }

    public function show(string $token): View
    {
        abort_unless(Str::isUlid($token), 404);
        $request = EventRequest::with('services')->where('public_token', $token)->firstOrFail();

        return view('public.request.track', [
            'request' => $request,
            'stage' => $request->status->publicStage(),
            'stages' => RequestStatus::publicStages(),
        ]);
    }
}
