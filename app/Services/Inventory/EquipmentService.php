<?php

namespace App\Services\Inventory;

use App\Enums\AssetStatusGroup;
use App\Enums\TrackingMode;
use App\Models\Equipment;
use App\Support\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    public function __construct(private ImageStore $images) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image = null): Equipment
    {
        $data = $this->normalise($data);

        if ($image) {
            $data['image_path'] = $this->images->store($image, 'equipment');
        }

        return Equipment::create($data + ['is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Equipment $equipment, array $data, ?UploadedFile $image = null, bool $removeImage = false): Equipment
    {
        $data = $this->normalise($data);

        if (isset($data['tracking_mode']) && $data['tracking_mode'] !== $equipment->tracking_mode->value && $this->hasInventory($equipment)) {
            throw ValidationException::withMessages(['tracking_mode' => 'Tracking can\'t change once this item has assets or stock.']);
        }

        $old = $equipment->image_path;

        if ($image) {
            $data['image_path'] = $this->images->store($image, 'equipment');
        } elseif ($removeImage) {
            $data['image_path'] = null;
        }

        $equipment->update($data);

        if ($old && $old !== $equipment->image_path) {
            $this->images->delete($old);
        }

        return $equipment;
    }

    /**
     * Archiving hides an item from the catalogue. Its units must be out of the
     * fleet first (retired, lost or archived assets; zero stock), so nothing
     * live disappears from view. History is kept (D16).
     */
    public function archive(Equipment $equipment): void
    {
        $liveAssets = $equipment->assets()->whereHas('status', fn ($q) => $q->whereNotIn('group', [AssetStatusGroup::Retired->value, AssetStatusGroup::Lost->value]))->count();
        $stock = (int) $equipment->stockLevels()->sum('quantity');

        if ($liveAssets > 0 || $stock > 0) {
            throw ValidationException::withMessages(['equipment' => $liveAssets > 0
                ? "{$liveAssets} unit(s) are still in the fleet. Retire or archive them first."
                : "{$stock} unit(s) are still in stock. Write them off first."]);
        }

        DB::transaction(fn () => $equipment->delete());
    }

    public function restore(Equipment $equipment): void
    {
        $equipment->restore();
    }

    private function hasInventory(Equipment $equipment): bool
    {
        return $equipment->assets()->withTrashed()->exists() || $equipment->stockLevels()->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        if (array_key_exists('asset_prefix', $data)) {
            $data['asset_prefix'] = $data['asset_prefix'] ? strtoupper(trim($data['asset_prefix'], '- ')) : null;
        }

        if (array_key_exists('sku', $data)) {
            $data['sku'] = strtoupper(trim($data['sku']));
        }

        if (($data['tracking_mode'] ?? null) === TrackingMode::Bulk->value) {
            $data['asset_prefix'] = null;
        }

        return $data;
    }
}
