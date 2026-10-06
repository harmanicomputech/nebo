<?php

namespace App\Http\Controllers\Public;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Services\Commercial\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The customer's own quotation, by its unguessable link (D62). Shows the
 * lines, totals and terms we chose to send them, never internal notes,
 * costs, staff or other customers' data. Revising a quotation changes the
 * link, so an old copy can't be accepted.
 */
class QuotationController extends Controller
{
    public function show(string $token, QuotationService $quotes): View
    {
        $quote = $this->find($token);
        $quotes->markViewed($quote);

        return view('quotations.document', ['quote' => $quote, 'internal' => false]);
    }

    public function respond(Request $request, string $token, QuotationService $quotes): RedirectResponse
    {
        $quote = $this->find($token);
        $data = $request->validate([
            'answer' => ['required', 'in:accepted,declined'],
            'name' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
            'agree' => ['required_if:answer,accepted', 'accepted_if:answer,accepted'],
        ], ['agree.accepted_if' => 'Tick the box to accept the terms.', 'agree.required_if' => 'Tick the box to accept the terms.']);

        $quotes->respond(null, $quote, $data['answer'] === 'accepted', $data['name'], $data['note'] ?? null);

        return redirect()->route('quotations.public', $token)->with('success', $data['answer'] === 'accepted'
            ? 'Thank you. We\'ve received your acceptance and will be in touch about the next steps.'
            : 'Thank you for letting us know. We\'ll be in touch.');
    }

    private function find(string $token): Quotation
    {
        abort_unless(Str::isUlid($token), 404);

        $quote = Quotation::with(['customer', 'items'])->where('public_token', $token)->firstOrFail();
        abort_if(in_array($quote->status, [QuotationStatus::Draft, QuotationStatus::Cancelled], true), 404);

        return $quote;
    }
}
