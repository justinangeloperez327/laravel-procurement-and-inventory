<?php

namespace App\Domain\Shared;

use LogicException;

/**
 * For workflow headers whose content is frozen once they leave an editable status. Only the columns
 * listed in workflowColumns() (status, approval stamps, totals) may still change, and only through
 * the domain actions that set them. The record can never be deleted once it has left draft.
 */
trait FreezesWhenFinal
{
    /** @return list<\BackedEnum> */
    abstract public static function editableStatuses(): array;

    /** @return list<string> */
    protected static function workflowColumns(): array
    {
        return ['status', 'submitted_at', 'approved_by', 'approved_at', 'total_amount', 'updated_at'];
    }

    public static function bootFreezesWhenFinal(): void
    {
        static::updating(function (self $model) {
            if ($model->wasEditable()) {
                return;
            }
            $changed = array_diff(array_keys($model->getDirty()), static::workflowColumns());
            if ($changed !== []) {
                throw new LogicException(class_basename($model).' is final; changes require a revision. Changed: '.implode(', ', $changed));
            }
        });
        static::deleting(fn (self $model) => $model->wasEditable() && $model->getOriginal('status') === static::editableStatuses()[0]
            ?: throw new LogicException(class_basename($model).' cannot be deleted once submitted.'));
    }

    public function isEditable(): bool
    {
        return in_array($this->status, static::editableStatuses(), true);
    }

    protected function wasEditable(): bool
    {
        return in_array($this->getOriginal('status'), static::editableStatuses(), true);
    }
}
