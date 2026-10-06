<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Writes created / updated / deleted / restored entries with before and after
 * values. Models can list attributes to leave out in $auditExclude and give a
 * readable name through auditLabel().
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            Audit::record('created', class_basename($model).' '.$model->auditLabel().' created', $model, null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableValues($model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);

            Audit::record('updated', class_basename($model).' '.$model->auditLabel().' updated', $model, $model->auditableValues($old), $changes);
        });

        static::deleted(function (Model $model) {
            $soft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

            Audit::record($soft ? 'archived' : 'deleted', class_basename($model).' '.$model->auditLabel().($soft ? ' archived' : ' deleted'), $model, $model->auditableValues($model->getAttributes()));
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                Audit::record('restored', class_basename($model).' '.$model->auditLabel().' restored', $model);
            });
        }
    }

    public function auditLabel(): string
    {
        return (string) ($this->getAttribute('reference') ?? $this->getAttribute('name') ?? '#'.$this->getKey());
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        foreach (property_exists($this, 'auditExclude') ? $this->auditExclude : [] as $key) {
            unset($values[$key]);
        }

        foreach (Audit::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
