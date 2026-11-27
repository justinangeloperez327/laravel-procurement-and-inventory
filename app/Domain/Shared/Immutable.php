<?php

namespace App\Domain\Shared;

use LogicException;

/**
 * Ledger-style records: never updated or deleted, only corrected by compensating entries.
 * PostgreSQL triggers (forbid_mutation) enforce the same rule in the database.
 */
trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(fn ($model) => throw new LogicException(class_basename($model).' records are immutable; post a compensating entry instead.'));
        static::deleting(fn ($model) => throw new LogicException(class_basename($model).' records are immutable; post a compensating entry instead.'));
    }
}
