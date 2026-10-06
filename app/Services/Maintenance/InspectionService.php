<?php

namespace App\Services\Maintenance;

use App\Models\ConditionReport;
use App\Models\EquipmentAsset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\Documents\DocumentStore;
use App\Services\Inventory\AssetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Inspections and damage reports (brief §27): records the condition through
 * AssetService, adds a condition report with photos, and optionally opens a
 * maintenance job for what was found.
 */
class InspectionService
{
    public function __construct(private AssetService $assets, private DocumentStore $documents, private MaintenanceService $maintenance) {}

    /**
     * @param  list<UploadedFile>  $photos
     * @param  array{type: string, priority: string, issue: string}|null  $job
     * @return array{report: ConditionReport, job: ?MaintenanceRecord}
     */
    public function record(User $actor, EquipmentAsset $asset, string $condition, ?string $note, array $photos = [], ?array $job = null): array
    {
        $paths = [];

        try {
            return DB::transaction(function () use ($actor, $asset, $condition, $note, $photos, $job, &$paths) {
                $from = $asset->condition;
                $this->assets->recordCondition($asset, $condition, $note);

                $report = ConditionReport::create([
                    'asset_id' => $asset->id, 'from_condition' => $from, 'to_condition' => $condition, 'source' => 'inspection',
                    'note' => $note, 'user_id' => $actor->id, 'user_name' => $actor->name, 'created_at' => now(),
                ]);

                foreach ($photos as $photo) {
                    $paths[] = $this->documents->store($photo, $report, 'photo')->path;
                }

                $record = $job ? $this->maintenance->report($actor, $asset->refresh(), $job + [
                    'source' => 'inspection',
                    'description' => $note,
                    'out_of_service' => true,
                ]) : null;

                return ['report' => $report, 'job' => $record];
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk(DocumentStore::DISK)->delete($path);
            }
            throw $e;
        }
    }
}
