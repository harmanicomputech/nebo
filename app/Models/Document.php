<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A private file attached to a record (D15). Served only through
 * DocumentController after DocumentPolicy checks the parent record.
 */
#[Fillable(['documentable_type', 'documentable_id', 'category', 'original_name', 'disk', 'path', 'mime', 'size', 'checksum', 'uploaded_by', 'uploaded_by_name', 'source'])]
class Document extends Model
{
    use Auditable, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = ['path', 'checksum', 'disk'];

    public function auditLabel(): string
    {
        return $this->original_name;
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function categoryLabel(): string
    {
        return $this->category ? app(Lookups::class)->label('document_category', $this->category) : 'Document';
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i ? 1 : 0).' '.$units[$i];
    }

    public function icon(): string
    {
        return match (true) {
            str_starts_with((string) $this->mime, 'image/') => 'monitor-down',
            str_contains((string) $this->mime, 'pdf') => 'file-text',
            default => 'file-text',
        };
    }
}
