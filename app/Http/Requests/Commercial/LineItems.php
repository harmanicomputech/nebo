<?php

namespace App\Http\Requests\Commercial;

use App\Enums\QuoteSection;
use App\Support\Format;
use Illuminate\Validation\Rule;

/** Shared validation for quotation and package lines (prices typed in Naira). */
trait LineItems
{
    protected function prepareLines(): void
    {
        $lines = collect($this->input('items', []))
            ->filter(fn ($l) => is_array($l) && filled($l['description'] ?? null))
            ->map(fn ($l) => array_merge($l, ['unit_price' => str_replace([',', ' ', '₦'], '', (string) ($l['unit_price'] ?? '0')) ?: '0']))
            ->values()->all();
        $this->merge(['items' => $lines]);
    }

    /** @return array<string, mixed> */
    protected function lineRules(int $max = 200): array
    {
        return [
            'items' => ['array', 'max:'.$max],
            'items.*.section' => ['required', Rule::enum(QuoteSection::class)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.days' => ['required', 'integer', 'min:1', 'max:365'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:10000000000'],
            'items.*.service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'items.*.equipment_id' => ['nullable', 'integer', Rule::exists('equipment', 'id')],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        return collect($this->validated('items') ?? [])->map(fn ($l) => [
            'section' => $l['section'], 'description' => $l['description'], 'quantity' => (int) $l['quantity'], 'days' => (int) $l['days'],
            'unit_price_kobo' => Format::toKobo($l['unit_price']) ?? 0, 'service_id' => $l['service_id'] ?? null, 'equipment_id' => $l['equipment_id'] ?? null,
        ])->all();
    }
}
