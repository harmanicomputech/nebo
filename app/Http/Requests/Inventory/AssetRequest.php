<?php

namespace App\Http\Requests\Inventory;

use App\Models\EquipmentAsset;
use App\Support\Format;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registering new units (route has {equipment}) or editing an asset's
 * details (route has {asset}). Status, location and condition changes after
 * registration go through their own actions so they reach the ledger.
 */
class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof EquipmentAsset
            ? $this->user()->can('update', $asset)
            : $this->user()->can('addAssets', $this->route('equipment'));
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => str_replace([',', ' ', '₦'], '', (string) $v) ?: null;
        $this->merge([
            'asset_tag' => strtoupper(trim((string) $this->input('asset_tag'))) ?: null,
            'serial_number' => trim((string) $this->input('serial_number')) ?: null,
            'barcode' => trim((string) $this->input('barcode')) ?: null,
            'purchase_cost' => $clean($this->input('purchase_cost')),
            'current_value' => $clean($this->input('current_value')),
        ]);
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        $equipmentId = $asset?->equipment_id ?? $this->route('equipment')?->id;
        $count = (int) $this->input('count', 1);

        $rules = [
            'asset_tag' => [$asset ? 'required' : 'nullable', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9\-_.\/]*$/', Rule::unique('equipment_assets', 'asset_tag')->ignore($asset)],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('equipment_assets', 'serial_number')->where('equipment_id', $equipmentId)->ignore($asset)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('equipment_assets', 'barcode')->ignore($asset)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'current_value' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'warranty_expires_on' => ['nullable', 'date'],
            'next_maintenance_due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $asset) {
            $rules += [
                'count' => ['required', 'integer', 'min:1', 'max:200'],
                'status_id' => ['required', 'integer', Rule::exists('asset_statuses', 'id')->where('is_manual', true)->where('is_active', true)],
                'condition' => ['required', Rule::in(app(Lookups::class)->activeKeys('condition'))],
                'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)->whereNull('deleted_at')],
            ];

            if ($count > 1) {
                $rules['serial_number'][] = 'prohibited';
                $rules['barcode'][] = 'prohibited';
                $rules['asset_tag'][] = 'prohibited';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'serial_number.prohibited' => 'Add serial numbers one unit at a time, or edit each unit after adding them.',
            'barcode.prohibited' => 'Add barcodes one unit at a time.',
            'asset_tag.prohibited' => 'Tags are generated when adding several units.',
            'asset_tag.regex' => 'Use capital letters, numbers and - _ . / only.',
            'serial_number.unique' => 'Another unit of this item already has that serial number.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assetData(): array
    {
        $data = $this->safe()->except(['purchase_cost', 'current_value', 'count']);

        if ($this->user()->can('inventory.costs')) {
            $data['purchase_cost_kobo'] = Format::toKobo($this->validated('purchase_cost'));
            $data['current_value_kobo'] = Format::toKobo($this->validated('current_value'));
        }

        return $data;
    }
}
