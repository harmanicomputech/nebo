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
        // Guard on this seeder's own data (the history seeder creates allocations too).
        if (EquipmentAllocation::whereHas('event', fn ($q) => $q->where('name', 'Marina Trust Town Hall'))->exists()) {
            return;
        }

        $admin = User::where('email', 'ada.okafor@nebostage.com')->firstOrFail();
        auth()->setUser($admin);
        $item = fn (string $sku) => Equipment::where('sku', $sku)->firstOrFail();

        // Today's town hall: a 6 m² screen and a small stage, allocated and dispatched.
        if ($today = Event::where('name', 'Marina Trust Town Hall')->first()) {
            foreach ([['LED-P391-L', 12], ['VID-VX600', 1], ['STG-PANEL', 16], ['STG-STAIR', 1], ['TRS-44-2M', 4], ['TRS-EGG', 32], ['TRS-PIN', 64]] as [$sku, $qty]) {
                $requirements->set($today, $item($sku), $qty);
                $item($sku)->isSerialized() ? $allocations->autoReserve($admin, $today, $item($sku), $qty) : $allocations->reserveBulk($admin, $today, $item($sku), $qty);
            }
            $list = $lists->sync($admin, $today);
            $lists->advanceAll($admin, $list, LoadStatus::Checked);
            $lists->dispatch($admin, $list->fresh());
        }

        // Jazz weekend: outdoor stage with roof and screens, partly allocated, load list being picked.
        if ($jazz = Event::where('name', 'Lagos Jazz Weekend')->first()) {
            foreach ([['LED-P391-S', 48, 40], ['LED-P391-L', 24, 24], ['VID-VX600', 2, 2], ['RIG-HOIST', 6, 4], ['TRS-46-3M', 16, 16], ['TRS-44-3M', 16, 16],
                ['ROOF-TOP', 6, 6], ['ROOF-SLEEVE', 6, 6], ['ROOF-BASE', 6, 6], ['STG-PANEL', 40, 40], ['STG-STAIR', 2, 2], ['TRS-PIN', 600, 0]] as [$sku, $need, $give]) {
                $requirements->set($jazz, $item($sku), $need);
                if ($give) {
                    $item($sku)->isSerialized() ? $allocations->autoReserve($admin, $jazz, $item($sku), $give) : $allocations->reserveBulk($admin, $jazz, $item($sku), $give);
                }
            }
            $list = $lists->sync($admin, $jazz);
            $list->items()->limit(10)->get()->each(fn ($i) => $lists->setItemStatus($admin, $i, LoadStatus::Picked));
        }

        // Product launch: wants a bigger screen than the 12 m² of small panels.
        if ($launch = Event::where('name', 'like', '%Volt Phone X%')->first()) {
            $requirements->set($launch, $item('LED-P391-S'), 64, '16 m² centre screen; hire in the extra 4 m²');
            $allocations->autoReserve($admin, $launch, $item('LED-P391-S'), 44);
            $requirements->set($launch, $item('VID-VX600'), 2);
            $allocations->autoReserve($admin, $launch, $item('VID-VX600'), 2);
            $requirements->set($launch, $item('STG-PANEL'), 30);
        }

        auth()->forgetUser();
    }
}
