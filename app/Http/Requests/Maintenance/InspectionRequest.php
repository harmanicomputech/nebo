<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePriority;
use App\Services\Documents\UploadRules;
use App\Support\Lookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Inspection / damage report with photos and an optional follow-up job. */
class InspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inspect', $this->route('asset'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['raise_job' => $this->boolean('raise_job') && $this->user()->can('maintenance.manage')]);
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', Rule::in(app(Lookups::class)->activeKeys('condition'))],
            'note' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.UploadRules::MAX_FILES],
            'photos.*' => array_merge(UploadRules::rule(), ['mimes:jpg,jpeg,png,webp']),
            'raise_job' => ['boolean'],
            'job_type' => ['exclude_unless:raise_job,true', 'required', Rule::in(app(Lookups::class)->activeKeys('maintenance_type'))],
            'job_priority' => ['exclude_unless:raise_job,true', 'required', Rule::enum(MaintenancePriority::class)],
            'job_issue' => ['exclude_unless:raise_job,true', 'required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return ['photos.*.mimes' => 'Photos must be JPG, PNG or WebP images.'];
    }

    /** @return array{type: string, priority: string, issue: string}|null */
    public function job(): ?array
    {
        return $this->validated('raise_job')
            ? ['type' => $this->validated('job_type'), 'priority' => $this->validated('job_priority'), 'issue' => $this->validated('job_issue')]
            : null;
    }
}
