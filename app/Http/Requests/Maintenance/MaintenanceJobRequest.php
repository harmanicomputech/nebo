<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePriority;
use App\Http\Requests\Concerns\ConvertsLocalTimes;
use App\Models\MaintenanceRecord;
use App\Models\Staff;
use App\Support\Lookups;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reporting a new maintenance job. Times are entered in Lagos time (D35). */
class MaintenanceJobRequest extends FormRequest
{
    use ConvertsLocalTimes;

    public function authorize(): bool
    {
        return $this->user()->can('create', MaintenanceRecord::class);
    }

    protected function prepareForValidation(): void
    {
        $this->convertLocalTimes(['scheduled_starts_at', 'scheduled_ends_at']);
        $this->merge(['out_of_service' => $this->boolean('out_of_service')]);
    }

    public function rules(): array
    {
        return [
            'asset_tag' => ['required', 'string', 'max:50', Rule::exists('equipment_assets', 'asset_tag')->whereNull('deleted_at')],
            'type' => ['required', Rule::in(app(Lookups::class)->activeKeys('maintenance_type'))],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
            'issue' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'technician_id' => ['nullable', Rule::exists(Staff::class, 'id')->whereNull('deleted_at')->where('is_active', true)],
            'scheduled_starts_at' => ['nullable', 'required_with:scheduled_ends_at', 'date'],
            'scheduled_ends_at' => ['nullable', 'required_with:scheduled_starts_at', 'date', 'after:scheduled_starts_at'],
            'out_of_service' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['asset_tag.exists' => 'No active asset has that tag.'];
    }

    /** @return array<string, mixed> */
    public function jobData(): array
    {
        $data = $this->safe()->except(['asset_tag']);
        foreach (['scheduled_starts_at', 'scheduled_ends_at'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? CarbonImmutable::parse($data[$field], 'UTC') : null;
        }

        return $data;
    }
}
