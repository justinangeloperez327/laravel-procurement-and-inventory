<?php

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues human-readable reference numbers such as PR-2026-0001, unique per agency, type and year.
 *
 * The counter row is locked FOR UPDATE, so concurrent requests are serialized. Call it inside the
 * same transaction that creates the record: if that transaction rolls back, so does the counter,
 * which means rolled-back work leaves no gaps.
 */
class ReferenceNumbers
{
    public function next(int $agencyId, string $type, int $year): string
    {
        if (! preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $type)) {
            throw new InvalidArgumentException("Invalid reference type [{$type}].");
        }

        return DB::transaction(function () use ($agencyId, $type, $year) {
            DB::table('reference_sequences')->insertOrIgnore([
                'agency_id' => $agencyId,
                'sequence_type' => $type,
                'year' => $year,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $query = DB::table('reference_sequences')
                ->where(['agency_id' => $agencyId, 'sequence_type' => $type, 'year' => $year]);

            $value = (int) (clone $query)->lockForUpdate()->value('last_value') + 1;
            $query->update(['last_value' => $value, 'updated_at' => now()]);

            return sprintf('%s-%d-%04d', $type, $year, $value);
        });
    }
}
