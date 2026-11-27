<?php

namespace App\Enums;

/** Stock ledger movement types (docs/10). */
enum StockMovementType: string
{
    case AcceptedReceipt = 'accepted_receipt';
    case Issuance = 'issuance';
    case Return = 'return';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case AdjustmentPlus = 'adjustment_plus';
    case AdjustmentMinus = 'adjustment_minus';
    case Disposal = 'disposal';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::AcceptedReceipt => 'Accepted receipt',
            self::Issuance => 'Issuance',
            self::Return => 'Return',
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::AdjustmentPlus => 'Adjustment plus',
            self::AdjustmentMinus => 'Adjustment minus',
            self::Disposal => 'Disposal',
            self::Reversal => 'Reversal',
        };
    }
}
