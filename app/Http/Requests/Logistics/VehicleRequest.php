<?php

namespace App\Http\Requests\Logistics;

use App\Enums\VehicleStatus;
use App\Models\Location;
use App\Models\Staff;
use App\Models\Vehicle;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle ? $this->user()->can('update', $vehicle) : $this->user()->can('create', Vehicle::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['registration' => strtoupper(preg_replace('/\s+/', ' ', trim((string) $this->input('registration'))))]);
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            'name' => ['required', 'string', 'max:120'],
            'registration' => ['required', 'string', 'max:30', Rule::unique(Vehicle::class)->ignore($vehicle)],
            'type' => ['required', Rule::in(array_merge(app(Lookups::class)->activeKeys('vehicle_type'), $vehicle ? [$vehicle->type] : []))],
            'capacity' => ['nullable', 'string', 'max:120'],
            'payload_kg' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::enum(VehicleStatus::class)],
            'default_driver_id' => ['nullable', Rule::exists(Staff::class, 'id')->whereNull('deleted_at')],
            'base_location_id' => ['nullable', Rule::exists(Location::class, 'id')->whereNull('deleted_at')],
            'insurance_expires_on' => ['nullable', 'date'],
            'roadworthiness_expires_on' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
