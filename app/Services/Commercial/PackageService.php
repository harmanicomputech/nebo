<?php

namespace App\Services\Commercial;

use App\Enums\QuoteSection;
use App\Models\ProductionPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageService
{
    /**
     * @param  array<string, mixed>  $data  name, description, event_type, is_active, sort_order, items
     */
    public function save(?ProductionPackage $package, array $data): ProductionPackage
    {
        return DB::transaction(function () use ($package, $data) {
            $package ??= new ProductionPackage;
            $package->fill(collect($data)->only(['name', 'description', 'event_type', 'is_active', 'sort_order'])->all());
            if (! $package->exists || $package->isDirty('name')) {
                $package->slug = $this->uniqueSlug($data['name'], $package->id);
            }
            $package->save();

            $package->items()->delete();
            foreach (array_values($data['items'] ?? []) as $i => $line) {
                $package->items()->create([
                    'section' => ($line['section'] instanceof QuoteSection ? $line['section'] : (QuoteSection::tryFrom((string) ($line['section'] ?? '')) ?? QuoteSection::Other))->value,
                    'description' => Str::limit(trim((string) $line['description']), 255, ''),
                    'service_id' => $line['service_id'] ?? null,
                    'equipment_id' => $line['equipment_id'] ?? null,
                    'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                    'days' => max(1, (int) ($line['days'] ?? 1)),
                    'unit_price_kobo' => max(0, (int) ($line['unit_price_kobo'] ?? 0)),
                    'sort_order' => $i,
                ]);
            }

            return $package;
        });
    }

    private function uniqueSlug(string $name, ?int $ignore): string
    {
        $base = Str::slug($name) ?: 'package';
        $slug = $base;
        for ($n = 2; ProductionPackage::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
