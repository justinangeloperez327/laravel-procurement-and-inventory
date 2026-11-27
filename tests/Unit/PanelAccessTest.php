<?php

use App\Models\User;
use Filament\Panel;

it('requires explicit admin access', function () {
    $user = new User;
    $panel = Panel::make()->id('admin');
    expect($user->canAccessPanel($panel))->toBeFalse();
    $user->forceFill(['is_admin' => true]);
    expect($user->canAccessPanel($panel))->toBeTrue();
});
