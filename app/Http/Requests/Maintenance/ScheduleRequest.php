<?php

namespace App\Http\Requests\Maintenance;

use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A recurring schedule for one unit or for every unit of an item. */
class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('maintenance.schedule');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->has('is_active') ? $this->boolean('is_active') : true]);
    }

    public function rules(): array
    {
        $creating = $this->route('schedule') === null;

        return [
            'scope' => [Rule::requiredIf($creating), Rule::in(['asset', 'equipment'])],
            'asset_id' => [Rule::requiredIf($creating && $this->input('scope') === 'asset'), 'nullable', Rule::exists('equipment_assets', 'id')->whereNull('deleted_at')],
            'equipment_id' => [Rule::requiredIf($creating && $this->input('scope') === 'equipment'), 'nullable', Rule::exists('equipment', 'id')->whereNull('deleted_at')->where('tracking_mode', 'serialized')],
            'type' => [Rule::requiredIf($creating), Rule::in(app(Lookups::class)->activeKeys('maintenance_type'))],
            'interval_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'next_due_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
