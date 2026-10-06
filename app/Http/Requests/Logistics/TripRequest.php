<?php

namespace App\Http\Requests\Logistics;

use App\Enums\TripDirection;
use App\Http\Requests\Concerns\ConvertsLocalTimes;
use App\Models\LogisticsTrip;
use App\Models\Staff;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Planning or editing a trip. Times are entered in Lagos time (D35). */
class TripRequest extends FormRequest
{
    use ConvertsLocalTimes;

    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip ? $this->user()->can('update', $trip) : $this->user()->can('create', LogisticsTrip::class);
    }

    protected function prepareForValidation(): void
    {
        $this->convertLocalTimes(['departs_at', 'arrives_at']);
    }

    public function rules(): array
    {
        $creating = $this->route('trip') === null;
        $staff = Rule::exists(Staff::class, 'id')->whereNull('deleted_at');

        return [
            'event_id' => [Rule::requiredIf($creating && $this->input('direction') !== 'transfer'), 'nullable', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'direction' => [Rule::requiredIf($creating), Rule::enum(TripDirection::class)],
            'vehicle_id' => ['nullable', Rule::exists(Vehicle::class, 'id')->whereNull('deleted_at')],
            'driver_id' => ['nullable', $staff],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'departs_at' => ['required', 'date'],
            'arrives_at' => ['required', 'date', 'after:departs_at'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'crew' => ['nullable', 'array', 'max:30'],
            'crew.*' => ['integer', $staff],
            'items' => ['nullable', 'array'],
            'items.*' => ['integer'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, mixed> */
    public function tripData(): array
    {
        $data = $this->safe()->except(['event_id']);
        $data['departs_at'] = CarbonImmutable::parse($data['departs_at'], 'UTC');
        $data['arrives_at'] = CarbonImmutable::parse($data['arrives_at'], 'UTC');
        $data['crew'] = array_values(array_diff(array_map('intval', $data['crew'] ?? []), [(int) ($data['driver_id'] ?? 0)]));

        return $data;
    }
}
