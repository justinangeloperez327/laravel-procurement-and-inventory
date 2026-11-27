<?php

namespace App\Enums;

/** Property classification. Never derived from the procurement method alone (docs/11). */
enum AssetClass: string
{
    case Consumable = 'consumable';
    case SemiExpendable = 'semi_expendable';
    case Ppe = 'ppe';

    public function label(): string
    {
        return match ($this) {
            self::Consumable => 'Consumable',
            self::SemiExpendable => 'Semi expendable',
            self::Ppe => 'PPE',
        };
    }
}
