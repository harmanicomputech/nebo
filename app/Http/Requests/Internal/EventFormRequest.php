<?php

namespace App\Http\Requests\Internal;

use App\Http\Requests\Concerns\ConvertsLocalTimes;
use App\Models\Event;
use App\Support\Format;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventFormRequest extends FormRequest
{
    use ConvertsLocalTimes;

    public const TIMES = ['setup_starts_at', 'starts_at', 'ends_at', 'breakdown_ends_at'];

    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event ? $this->user()->can('update', $event) : $this->user()->can('create', Event::class);
    }

    protected function prepareForValidation(): void
    {
        $this->convertLocalTimes(self::TIMES);
        $this->merge(['budget' => str_replace([',', ' ', '₦'], '', (string) $this->input('budget')) ?: null]);
    }

    public function rules(): array
    {
        $fromRequest = $this->route('request') !== null;

        return [
            'name' => ['required', 'string', 'max:200'],
            'customer_id' => [$fromRequest || $this->route('event') ? 'nullable' : 'required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'event_type' => ['required', Rule::in(array_merge(app(Lookups::class)->activeKeys('event_type'), [$this->route('event')?->event_type]))],
            'venue' => ['required', 'string', 'max:500'],
            'setup_starts_at' => ['required', 'date'],
            'starts_at' => ['required', 'date', 'after_or_equal:setup_starts_at'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'breakdown_ends_at' => ['required', 'date', 'after_or_equal:ends_at'],
            'project_manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'production_manager_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where('is_active', true)],
            'services' => ['array'],
            'services.*' => ['integer', 'exists:services,id'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'production_requirements' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.after_or_equal' => 'The event must start after setup begins.',
            'breakdown_ends_at.after_or_equal' => 'Breakdown must finish after the event ends.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        $data = $this->safe()->except(['services', 'budget']);

        if ($this->user()->can('financial.view')) {
            $data['budget_kobo'] = Format::toKobo($this->validated('budget'));
        }

        return array_filter($data, fn ($v, $k) => $k !== 'customer_id' || $v !== null, ARRAY_FILTER_USE_BOTH);
    }
}
