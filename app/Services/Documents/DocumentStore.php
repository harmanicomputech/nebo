<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Support\Audit\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores files privately under random names (D15) and links them to a record.
 * The original name is kept only as data for display and download.
 */
class DocumentStore
{
    public const DISK = 'local';

    public function store(UploadedFile $file, Model $owner, ?string $category = null, string $source = 'internal'): Document
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $folder = 'documents/'.Str::kebab(class_basename($owner)).'/'.$owner->getKey();
        $path = $file->storeAs($folder, Str::random(40).'.'.$extension, self::DISK);
        $user = auth()->user();

        return Document::create([
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->getKey(),
            'category' => $category,
            'original_name' => Str::limit(preg_replace('/[^\w.\- ()]+/u', '_', $file->getClientOriginalName()), 200, ''),
            'disk' => self::DISK,
            'path' => $path,
            'mime' => UploadRules::sniff($file),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $user?->id,
            'uploaded_by_name' => $user?->name,
            'source' => $source,
        ]);
    }

    /** Archives the record; the file stays so history and audits can refer to it. */
    public function remove(Document $document): void
    {
        $document->delete();
        Audit::record('document_removed', "Document {$document->original_name} removed", $document);
    }

    public function exists(Document $document): bool
    {
        return Storage::disk($document->disk)->exists($document->path);
    }
}
