<?php

namespace App\Http\Requests\Commercial;

use App\Models\Customer;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer ? $this->user()->can('update', $customer) : $this->user()->can('create', Customer::class);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(array_merge(app(Lookups::class)->activeKeys('customer_type'), $customer?->type ? [$customer->type] : []))],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
