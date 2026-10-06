<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockBucket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementRequest extends FormRequest
{
    public const ACTIONS = ['receive', 'transfer', 'quarantine', 'release', 'write_off', 'adjust'];

    public function authorize(): bool
    {
        return $this->user()->can('adjustStock', $this->route('equipment'));
    }

    public function rules(): array
    {
        $location = ['required', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')];
        $action = $this->input('action');

        return array_filter([
            'action' => ['required', Rule::in(self::ACTIONS)],
            'location_id' => $location,
            'to_location_id' => $action === 'transfer' ? array_merge($location, ['different:location_id']) : null,
            'quantity' => ['required', 'integer', $action === 'adjust' ? 'min:0' : 'min:1', 'max:1000000'],
            'bucket' => in_array($action, ['write_off', 'adjust'], true) ? ['required', Rule::enum(StockBucket::class)] : null,
            'purchased' => ['nullable', 'boolean'],
            'note' => [in_array($action, ['write_off', 'adjust'], true) ? 'required' : 'nullable', 'string', 'max:500'],
        ]);
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Give a reason; it is kept in the stock history.',
            'to_location_id.different' => 'Choose a different destination.',
        ];
    }
}
