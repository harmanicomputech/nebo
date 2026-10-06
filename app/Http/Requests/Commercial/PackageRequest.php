<?php

namespace App\Http\Requests\Commercial;

use App\Models\ProductionPackage;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackageRequest extends FormRequest
{
    use LineItems;

    public function authorize(): bool
    {
        $package = $this->route('package');

        return $package ? $this->user()->can('update', $package) : $this->user()->can('create', ProductionPackage::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareLines();
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'event_type' => ['nullable', Rule::in(app(Lookups::class)->activeKeys('event_type'))],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            ...$this->lineRules(100),
        ];
    }

    /** @return array<string, mixed> */
    public function packageData(): array
    {
        return $this->safe()->only(['name', 'description', 'event_type', 'is_active']) + ['sort_order' => (int) $this->validated('sort_order'), 'items' => $this->lines()];
    }
}
