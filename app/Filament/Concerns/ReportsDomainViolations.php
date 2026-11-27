<?php

namespace App\Filament\Concerns;

use App\Domain\Shared\DomainRuleViolation;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Runs a domain action and turns rule violations into a notification instead of an error page. */
trait ReportsDomainViolations
{
    protected static function guarded(Action $action, Closure $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainRuleViolation $e) {
            Notification::make()->danger()->title('Change not allowed')->body($e->getMessage())->send();
            $action->halt();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }
}
