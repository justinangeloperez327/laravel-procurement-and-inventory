<?php

namespace App\Domain\Shared;

use LogicException;

/**
 * For line items: they may be created, changed or deleted only while their parent is editable.
 */
trait BelongsToEditableParent
{
    abstract protected function editableParent(): ?object;

    public static function bootBelongsToEditableParent(): void
    {
        $guard = function (self $line) {
            $parent = $line->editableParent();
            if ($parent !== null && ! $parent->isEditable()) {
                throw new LogicException(class_basename($parent).' is final; its lines cannot change.');
            }
        };
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }
}
