<?php

namespace App\Http\Requests\Booking;

use App\Models\Service;
use App\Services\Documents\UploadRules;
use App\Support\Lookups;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The public Event Production Request form. Everything is validated here,
 * server-side; the browser checks are only a convenience.
 *
 * Bot protection without a CAPTCHA: a hidden honeypot field must stay empty,
 * and the form must have been open for a few seconds (an encrypted start time).
 */
class PublicEventRequest extends FormRequest
{
    public const MIN_FILL_SECONDS = 3;

    protected function prepareForValidation(): void
    {
        // datetime-local inputs are Lagos time; store and compare in UTC (D20).
        $toUtc = function (string $field) {
            $value = $this->input($field);
            try {
                return filled($value) ? Carbon::parse((string) $value, config('nebo.display_timezone'))->utc()->toDateTimeString() : null;
            } catch (\Throwable) {
                return $value; // invalid: let the date rule report it
            }
        };

        $this->merge([
            'setup_at' => $toUtc('setup_at'),
            'starts_at' => $toUtc('starts_at'),
            'ends_at' => $toUtc('ends_at'),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'has_existing_design' => $this->input('has_existing_design') === 'yes',
            'services' => array_values((array) $this->input('services', [])),
        ]);
    }

    public function rules(): array
    {
        $lookups = app(Lookups::class);
        $offered = Service::query()->offered()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $timezone = config('nebo.display_timezone');

        return [
            'event_name' => ['required', 'string', 'max:200'],
            'event_type' => ['required', Rule::in($lookups->activeKeys('event_type'))],
            'event_type_other' => ['nullable', 'required_if:event_type,other', 'string', 'max:150'],
            'event_date' => ['required', 'date', 'after_or_equal:'.now($timezone)->toDateString(), 'before:'.now($timezone)->addYears(3)->toDateString()],
            'venue' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) {
                if (! PhoneNumber::isValid((string) $value)) {
                    $fail('Enter a valid phone number, e.g. 0803 123 4567.');
                }
            }],
            'email' => ['required', 'email:rfc', 'max:255'],
            'services' => ['required', 'array', 'min:1', 'max:20'],
            'services.*' => ['string', Rule::in(array_merge($offered, ['other']))],
            'services_other' => [Rule::requiredIf(in_array('other', (array) $this->input('services'), true)), 'nullable', 'string', 'max:255'],
            'requirements' => ['required', 'string', 'min:10', 'max:10000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:60'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'setup_at' => ['required', 'date', 'after:now'],
            'has_existing_design' => ['boolean'],
            'files' => ['nullable', 'array', 'max:'.UploadRules::MAX_FILES],
            'files.*' => UploadRules::rule(),
            'budget_range' => ['required', Rule::in($lookups->activeKeys('budget_range'))],
            'contact_person' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'additional_info' => ['nullable', 'string', 'max:5000'],
            'submission_key' => ['required', 'uuid'],
            'website' => ['nullable', 'max:0'], // honeypot
            'form_started' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            try {
                $started = (int) Crypt::decryptString((string) $this->input('form_started'));
            } catch (DecryptException) {
                $started = 0;
            }

            $elapsed = now()->timestamp - $started;
            if ($elapsed < self::MIN_FILL_SECONDS || $elapsed > 86400) {
                $validator->errors()->add('form', 'Something went wrong with the form. Please check your details and submit again.');
            }

            // The setup must happen by the end of the event day.
            $tz = config('nebo.display_timezone');
            $setup = filled($this->input('setup_at')) && strtotime((string) $this->input('setup_at')) ? Carbon::parse($this->input('setup_at'), 'UTC')->setTimezone($tz) : null;
            $eventDay = filled($this->input('event_date')) && strtotime((string) $this->input('event_date')) ? Carbon::parse($this->input('event_date'), $tz) : null;
            if ($setup && $eventDay && $setup->copy()->startOfDay()->gt($eventDay->copy()->addDays(max(0, (int) $this->input('duration_days', 1) - 1)))) {
                $validator->errors()->add('setup_at', 'The setup must be before or during the event.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'event_date.after_or_equal' => 'The event date can\'t be in the past.',
            'event_type_other.required_if' => 'Tell us the type of event.',
            'services.required' => 'Choose at least one service.',
            'services_other.required' => 'Tell us which other service you need.',
            'setup_at.after' => 'The setup time must be in the future.',
            'requirements.min' => 'Tell us a little more about your requirements.',
            'website.max' => 'Something went wrong with the form. Please try again.',
            'ends_at.after_or_equal' => 'The end must be after the start.',
        ];
    }

    public function attributes(): array
    {
        return ['setup_at' => 'setup date and time', 'event_date' => 'event date', 'budget_range' => 'budget', 'files.*' => 'file'];
    }
}
