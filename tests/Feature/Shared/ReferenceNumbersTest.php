<?php

use App\Domain\Shared\ReferenceNumbers;
use App\Models\Agency;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->numbers = app(ReferenceNumbers::class));

it('issues sequential numbers per agency, type and year', function () {
    $a = Agency::factory()->create();
    $b = Agency::factory()->create();

    expect($this->numbers->next($a->id, 'PR', 2026))->toBe('PR-2026-0001')
        ->and($this->numbers->next($a->id, 'PR', 2026))->toBe('PR-2026-0002')
        ->and($this->numbers->next($a->id, 'PR', 2027))->toBe('PR-2027-0001')
        ->and($this->numbers->next($a->id, 'PO', 2026))->toBe('PO-2026-0001')
        ->and($this->numbers->next($b->id, 'PR', 2026))->toBe('PR-2026-0001');
});

it('leaves no gap when the surrounding transaction rolls back', function () {
    $agency = Agency::factory()->create();
    $this->numbers->next($agency->id, 'PR', 2026);

    try {
        DB::transaction(function () use ($agency) {
            $this->numbers->next($agency->id, 'PR', 2026);
            throw new RuntimeException('record creation failed');
        });
    } catch (RuntimeException) {
    }

    expect($this->numbers->next($agency->id, 'PR', 2026))->toBe('PR-2026-0002');
});

it('rejects malformed reference types', function () {
    $this->numbers->next(Agency::factory()->create()->id, 'pr; drop', 2026);
})->throws(InvalidArgumentException::class);
