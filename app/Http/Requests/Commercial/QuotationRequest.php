<?php

namespace App\Http\Requests\Commercial;

use App\Models\Quotation;
use App\Support\Format;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    use LineItems;

    public function authorize(): bool
    {
        $quote = $this->route('quotation');

        return $quote ? $this->user()->can('update', $quote) : $this->user()->can('create', Quotation::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareLines();
        $this->merge([
            'discount' => str_replace([',', ' ', '₦'], '', (string) $this->input('discount')) ?: '0',
            'tax_percent' => (string) $this->input('tax_percent', '0') === '' ? '0' : $this->input('tax_percent'),
        ]);
    }

    public function rules(): array
    {
        $creating = $this->route('quotation') === null;

        return [
            'customer_id' => [Rule::requiredIf($creating), 'nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'event_request_id' => ['nullable', Rule::exists('event_requests', 'id')],
            'event_id' => ['nullable', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:200'],
            'valid_until' => ['required', 'date', $creating ? 'after_or_equal:today' : 'date'],
            'discount' => ['numeric', 'min:0', 'max:10000000000'],
            'tax_percent' => ['numeric', 'min:0', 'max:50'],
            'intro' => ['nullable', 'string', 'max:3000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            ...$this->lineRules(),
        ];
    }

    /** @return array<string, mixed> */
    public function quoteData(): array
    {
        return [
            'title' => $this->validated('title'),
            'valid_until' => $this->validated('valid_until'),
            'discount_kobo' => Format::toKobo($this->validated('discount')) ?? 0,
            'tax_rate_bp' => (int) round(((float) $this->validated('tax_percent')) * 100),
            'intro' => $this->validated('intro'),
            'terms' => $this->validated('terms'),
            'event_request_id' => $this->validated('event_request_id'),
            'event_id' => $this->validated('event_id'),
            'items' => $this->lines(),
        ];
    }
}
