<?php

namespace App\Http\Requests\Maintenance;

use App\Support\Format;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('record'));
    }

    public function rules(): array
    {
        return [
            'work_done' => ['required', 'string', 'max:5000'],
            'outcome_condition' => ['required', Rule::in(app(Lookups::class)->activeKeys('condition'))],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'parts_used' => ['nullable', 'string', 'max:2000'],
            'next_due_on' => ['nullable', 'date', 'after:today'],
        ];
    }

    /** @return array{work_done: string, outcome_condition: string, cost_kobo: ?int, parts_used: ?string, next_due_on: ?string} */
    public function completion(): array
    {
        $data = $this->validated();

        return [
            'work_done' => $data['work_done'],
            'outcome_condition' => $data['outcome_condition'],
            // Cost is only recorded by people who may see costs.
            'cost_kobo' => $this->user()->can('inventory.costs') && filled($data['cost'] ?? null) ? Format::toKobo($data['cost']) : null,
            'parts_used' => $data['parts_used'] ?? null,
            'next_due_on' => $data['next_due_on'] ?? null,
        ];
    }
}
