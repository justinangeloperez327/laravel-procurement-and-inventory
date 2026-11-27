<?php

use App\Domain\Shared\ReferenceNumbers;
use App\Models\Agency;

it('never issues the same reference number twice under concurrent load', function () {
    $agency = Agency::factory()->create();

    $results = concurrently(6, function () use ($agency) {
        $numbers = app(ReferenceNumbers::class);

        return array_map(fn () => $numbers->next($agency->id, 'PR', 2026), range(1, 15));
    });

    $issued = array_merge(...$results);
    $expected = array_map(fn ($n) => sprintf('PR-2026-%04d', $n), range(1, 90));
    sort($issued);

    expect($issued)->toBe($expected);
});
