<?php

namespace Database\Seeders;

use App\Enums\LoadStatus;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Event;
use App\Models\User;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\LoadListService;
use App\Services\Allocation\RequirementService;
use Illuminate\Database\Seeder;

/** Demo requirements, allocations and a dispatched load list (never production). */
class AllocationDemoSeeder extends Seeder
{
    public function run(RequirementService $requirements, AllocationService $allocations, LoadListService $lists): void
    {
        if (EquipmentAllocation::exists()) {
            return;
        }

        $admin = User::where('email', 'admin@nebostage.test')->firstOrFail();
        auth()->login($admin);
        $item = fn (string $sku) => Equipment::where('sku', $sku)->firstOrFail();

        // Today's town hall: allocated and dispatched.
        if ($today = Event::where('name', '[Demo] FirstCorp Town Hall')->first()) {
            foreach ([['DEMO-ML-01', 4], ['DEMO-S4-03', 6], ['DEMO-CL-06', 1], ['DEMO-BLK-02', 20]] as [$sku, $qty]) {
                $requirements->set($today, $item($sku), $qty);
                $item($sku)->isSerialized() ? $allocations->autoReserve($admin, $today, $item($sku), $qty) : $allocations->reserveBulk($admin, $today, $item($sku), $qty);
            }
            $list = $lists->sync($admin, $today);
            $lists->advanceAll($admin, $list, LoadStatus::Checked);
            $lists->dispatch($admin, $list->fresh());
        }

        // Jazz weekend: partly allocated, load list being picked.
        if ($jazz = Event::where('name', '[Demo] Lagos Jazz Weekend')->first()) {
            foreach ([['DEMO-ML-01', 16, 12], ['DEMO-MW-02', 12, 12], ['DEMO-FS-04', 4, 4], ['DEMO-HST-10', 8, 8], ['DEMO-BLK-01', 40, 40], ['DEMO-GMA-05', 1, 0]] as [$sku, $need, $give]) {
                $requirements->set($jazz, $item($sku), $need);
                if ($give) {
                    $item($sku)->isSerialized() ? $allocations->autoReserve($admin, $jazz, $item($sku), $give) : $allocations->reserveBulk($admin, $jazz, $item($sku), $give);
                }
            }
            $list = $lists->sync($admin, $jazz);
            $list->items()->limit(10)->get()->each(fn ($i) => $lists->setItemStatus($admin, $i, LoadStatus::Picked));
        }

        // Product launch: needs more moving lights than the fleet can serve.
        if ($launch = Event::where('name', 'like', '%Volt Phone X%')->first()) {
            $requirements->set($launch, $item('DEMO-ML-01'), 24, 'Full rig per lighting plot');
            $allocations->autoReserve($admin, $launch, $item('DEMO-ML-01'), 20);
            $requirements->set($launch, $item('DEMO-GMA-05'), 1);
            $allocations->autoReserve($admin, $launch, $item('DEMO-GMA-05'), 1);
            $requirements->set($launch, $item('DEMO-BLK-03'), 60);
        }

        auth()->logout();
    }
}
