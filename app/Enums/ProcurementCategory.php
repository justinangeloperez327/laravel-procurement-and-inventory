<?php

namespace App\Enums;

/** Procurement category. */
enum ProcurementCategory: string
{
    case Goods = 'goods';
    case Infrastructure = 'infrastructure';
    case ConsultingServices = 'consulting_services';

    public function label(): string
    {
        return match ($this) {
            self::Goods => 'Goods',
            self::Infrastructure => 'Infrastructure',
            self::ConsultingServices => 'Consulting services',
        };
    }
}
