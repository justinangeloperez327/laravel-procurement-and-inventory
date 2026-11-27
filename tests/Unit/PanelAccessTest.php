<?php

namespace Tests\Unit;

use App\Models\User;
use Filament\Panel;
use PHPUnit\Framework\TestCase;

class PanelAccessTest extends TestCase
{
    public function test_panel_access_requires_explicit_admin_grant(): void
    {
        $user = new User;
        $panel = Panel::make()->id('admin');
        $this->assertFalse($user->canAccessPanel($panel));
        $user->forceFill(['is_admin' => true]);
        $this->assertTrue($user->canAccessPanel($panel));
    }
}
