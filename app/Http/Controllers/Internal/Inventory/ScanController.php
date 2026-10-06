<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Http\Controllers\Controller;
use App\Models\EquipmentAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Target of the QR code on each asset label. The token is opaque (a ULID),
 * so labels reveal nothing, and the page still requires sign-in.
 */
class ScanController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $asset = EquipmentAsset::withTrashed()->where('qr_token', $token)->first();

        abort_unless($asset, 404, 'No asset has this code.');
        $this->authorize('view', $asset);

        return redirect()->route('app.inventory.assets.show', $asset->id);
    }
}
