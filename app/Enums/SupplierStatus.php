<?php

namespace App\Enums;

/** Supplier registry status. */
enum SupplierStatus: string
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
}
