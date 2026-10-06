<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\LogisticsTrip;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\UploadRules;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, download and remove documents on any record type registered in
 * OWNERS. Access follows the parent record's policy (DocumentPolicy).
 */
class DocumentController extends Controller
{
    /** Route key => model class allowed to own documents. */
    public const OWNERS = [
        'request' => EventRequest::class,
        'event' => Event::class,
        'maintenance' => MaintenanceRecord::class,
        'trip' => LogisticsTrip::class,
        'vehicle' => Vehicle::class,
    ];

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        // Images preview inline; everything else (PDF included) downloads, so the
        // sandboxing CSP below never blocks a browser's PDF viewer.
        $inline = in_array($document->mime, ['image/jpeg', 'image/png', 'image/webp'], true);

        return Storage::disk($document->disk)->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }

    public function store(Request $request, DocumentStore $store, Lookups $lookups, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(self::OWNERS[$type]), 404);
        /** @var Model $owner */
        $owner = self::OWNERS[$type]::findOrFail($id);
        abort_unless($request->user()->can('documents.manage') && Gate::allows('update', $owner), 403);

        $data = $request->validate([
            'files' => ['required', 'array', 'max:'.UploadRules::MAX_FILES],
            'files.*' => UploadRules::rule(),
            'category' => ['nullable', Rule::in($lookups->activeKeys('document_category'))],
        ]);

        foreach ($request->file('files') as $file) {
            $store->store($file, $owner, $data['category'] ?? null);
        }

        return back()->with('success', count($data['files']).' '.str('file')->plural(count($data['files'])).' uploaded.');
    }

    public function destroy(Document $document, DocumentStore $store): RedirectResponse
    {
        $this->authorize('delete', $document);
        $store->remove($document);

        return back()->with('success', "{$document->original_name} removed.");
    }
}
