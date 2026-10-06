<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Enums\StockBucket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockMovementRequest;
use App\Models\Equipment;
use App\Models\Location;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function __invoke(StockMovementRequest $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validated();
        $location = Location::findOrFail($data['location_id']);
        $qty = (int) $data['quantity'];
        $note = $data['note'] ?? null;
        $unit = $equipment->unitLabel();

        switch ($data['action']) {
            case 'receive':
                $this->stock->receive($equipment, $location, $qty, (bool) ($data['purchased'] ?? false), $note);
                $message = "Received {$qty} × {$unit} at {$location->name}.";
                break;
            case 'transfer':
                $to = Location::findOrFail($data['to_location_id']);
                $this->stock->transfer($equipment, $location, $to, $qty, $note);
                $message = "Moved {$qty} × {$unit} from {$location->name} to {$to->name}.";
                break;
            case 'quarantine':
                $this->stock->moveBucket($equipment, $location, StockBucket::Available, StockBucket::Quarantine, $qty, $note);
                $message = "{$qty} × {$unit} quarantined at {$location->name}.";
                break;
            case 'release':
                $this->stock->moveBucket($equipment, $location, StockBucket::Quarantine, StockBucket::Available, $qty, $note);
                $message = "{$qty} × {$unit} released back to available.";
                break;
            case 'write_off':
                $this->stock->writeOff($equipment, $location, StockBucket::from($data['bucket']), $qty, $note);
                $message = "{$qty} × {$unit} written off.";
                break;
            default: // adjust
                $this->stock->adjust($equipment, $location, StockBucket::from($data['bucket']), $qty, $note);
                $message = "Stock count recorded for {$location->name}.";
        }

        return redirect()->route('app.inventory.equipment.show', [$equipment, 'tab' => 'stock'])->with('success', $message);
    }
}
