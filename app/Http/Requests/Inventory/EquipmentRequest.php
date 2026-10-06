<?php

namespace App\Http\Requests\Inventory;

use App\Enums\TrackingMode;
use App\Models\Equipment;
use App\Support\Format;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipment = $this->route('equipment');

        return $equipment instanceof Equipment
            ? $this->user()->can('update', $equipment)
            : $this->user()->can('create', Equipment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku'))),
            'asset_prefix' => strtoupper(trim((string) $this->input('asset_prefix'), '- ')) ?: null,
            'replacement_value' => str_replace([',', ' ', '₦'], '', (string) $this->input('replacement_value')) ?: null,
            'day_rate' => str_replace([',', ' ', '₦'], '', (string) $this->input('day_rate')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $equipment = $this->route('equipment');
        $serialized = $this->input('tracking_mode', $equipment?->tracking_mode?->value) === TrackingMode::Serialized->value;

        return [
            'category_id' => ['required', 'integer', Rule::exists('equipment_categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9\-_.\/]*$/', Rule::unique('equipment', 'sku')->ignore($equipment)],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'tracking_mode' => ['required', Rule::enum(TrackingMode::class)],
            'unit' => ['required', Rule::in(array_merge(app(Lookups::class)->activeKeys('unit'), $equipment ? [$equipment->unit] : []))],
            'asset_prefix' => array_merge(['nullable'], $serialized ? [] : ['prohibited'], ['string', 'max:10', 'regex:/^[A-Z0-9]+$/']),
            'description' => ['nullable', 'string', 'max:5000'],
            'replacement_value' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'day_rate' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.regex' => 'Use capital letters, numbers and - _ . / only.',
            'asset_prefix.regex' => 'Use capital letters and numbers only, e.g. ML.',
            'asset_prefix.prohibited' => 'Only serialized items use asset tags.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function equipmentData(): array
    {
        $data = $this->safe()->except(['image', 'remove_image', 'replacement_value', 'day_rate']);

        // Values are only accepted from people allowed to see them.
        if ($this->user()->can('inventory.costs')) {
            $data['replacement_value_kobo'] = Format::toKobo($this->validated('replacement_value'));
            $data['day_rate_kobo'] = Format::toKobo($this->validated('day_rate'));
        }

        return $data;
    }
}
