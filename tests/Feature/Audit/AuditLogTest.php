<?php

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('records creates and updates of master data with the actor', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $agency = Agency::factory()->create(['name' => 'Old']);
    $agency->update(['name' => 'New']);

    $update = AuditLog::where('event', 'updated')->where('auditable_type', $agency->getMorphClass())->sole();
    expect(AuditLog::where('event', 'created')->where('auditable_id', $agency->id)->where('auditable_type', $agency->getMorphClass())->exists())->toBeTrue()
        ->and($update->user_id)->toBe($admin->id)
        ->and($update->old_values)->toBe(['name' => 'Old'])
        ->and($update->new_values)->toBe(['name' => 'New']);
});

it('never stores password hashes', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'a-new-long-password']);

    AuditLog::where('auditable_type', $user->getMorphClass())->get()->each(function (AuditLog $log) {
        expect($log->new_values ?? [])->not->toHaveKey('password')->not->toHaveKey('remember_token');
    });
});

it('refuses updates and deletes through the model', function () {
    $log = AuditLog::record('test.event');

    expect(fn () => $log->update(['event' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('refuses updates and deletes at the database level', function () {
    $log = AuditLog::record('test.event');

    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['event' => 'tampered']))->toThrow(QueryException::class);
});

it('refuses deletes at the database level', function () {
    $log = AuditLog::record('test.event');

    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->delete())->toThrow(QueryException::class);
});

it('records sign-in and failed sign-in attempts', function () {
    $user = User::factory()->create();

    auth()->attempt(['email' => $user->email, 'password' => 'wrong-password']);
    auth()->attempt(['email' => $user->email, 'password' => 'password']);

    expect(AuditLog::where('event', 'auth.failed')->value('context'))->toBe(['email' => $user->email])
        ->and(AuditLog::where('event', 'auth.login')->where('auditable_id', $user->id)->exists())->toBeTrue();
});
