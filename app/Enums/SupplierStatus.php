<?php

namespace App\Enums;

use App\Domain\Shared\HasTransitions;

/** Supplier registry status. */
enum SupplierStatus: string implements HasTransitions
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Blacklisted = 'blacklisted';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Blacklisted => 'Blacklisted',
            self::Inactive => 'Inactive',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Inactive],
            self::Active => [self::Suspended, self::Blacklisted, self::Inactive],
            self::Suspended => [self::Active, self::Blacklisted, self::Inactive],
            self::Blacklisted => [self::Active, self::Inactive],
            self::Inactive => [self::Pending],
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Suspended, self::Blacklisted, self::Inactive], true);
    }
}
