<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);
it('redirects guests to login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
it('denies non-admin users', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});
it('allows administrators to access the dashboard', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();
    $this->actingAs($user)->get('/admin')->assertOk();
});
it('renders the login page', function () {
    $this->get('/admin/login')->assertOk();
});
