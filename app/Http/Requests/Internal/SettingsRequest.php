<?php

namespace App\Http\Requests\Internal;

use App\Services\ReferenceGenerator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    /** Settings editable on the General page. Form fields use _ for the dots. */
    public const KEYS = [
        'company.name', 'company.email', 'company.phone', 'company.address', 'company.coverage',
        'references.request', 'references.event', 'references.quotation', 'references.load_list', 'references.maintenance', 'references.trip',
        'notifications.request_recipients', 'availability.buffer_hours', 'maintenance.reminder_days',
        'quotations.validity_days', 'quotations.vat_percent', 'quotations.terms',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        $reference = ['required', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail) {
            if (! ReferenceGenerator::isValidFormat((string) $value)) {
                $fail('Use letters, numbers, - / _ and the tokens {YYYY}, {YY}, {MM} and {SEQ:n} (n from 1 to 12, required).');
            }
        }];

        return [
            'company_name' => ['required', 'string', 'max:120'],
            'company_email' => ['required', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_coverage' => ['nullable', 'string', 'max:120'],
            'references_request' => $reference,
            'references_event' => $reference,
            'references_quotation' => $reference,
            'references_load_list' => $reference,
            'references_maintenance' => $reference,
            'references_trip' => $reference,
            'availability_buffer_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'maintenance_reminder_days' => ['required', 'integer', 'min:0', 'max:90'],
            'quotations_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'quotations_vat_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'quotations_terms' => ['nullable', 'string', 'max:5000'],
            'notifications_request_recipients' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, Closure $fail) {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail("\"{$email}\" is not a valid email address.");
                    }
                }
            }],
        ];
    }
}
