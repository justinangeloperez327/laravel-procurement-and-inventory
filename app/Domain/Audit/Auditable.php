<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;

/**
 * Records create/update/delete events of the model into the append-only audit log.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => AuditLog::record('created', $model, null, $model->auditableAttributes($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            AuditLog::record('updated', $model, $old, $changes);
        });

        static::deleted(fn ($model) => AuditLog::record('deleted', $model, $model->auditableAttributes($model->getOriginal()), null));
    }

    /** @param array<string, mixed> $attributes */
    protected function auditableAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(array_merge($this->getHidden(), ['password', 'remember_token'])));
    }
}
