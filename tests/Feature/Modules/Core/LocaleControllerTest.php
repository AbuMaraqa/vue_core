<?php

use App\Models\User;
use App\Modules\Core\Settings\LocalizationSettings;
use Inertia\Testing\AssertableInertia as Assert;

test('switching the language reloads the current page in that language', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->from('/en/settings/profile?tab=1')
        ->put(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(url('/ar/settings/profile?tab=1'))
        ->assertSessionHas('locale', 'ar');

    expect($user->fresh()->locale)->toBe('ar');
});

test('switching the language of an inertia page forces a full page load', function () {
    $this->from('/en')
        ->put(route('locale.update'), ['locale' => 'ar'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/ar'));
});

test('the language switch never redirects to another site', function () {
    $this->withHeader('referer', 'https://evil.example/en/admin')
        ->put(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(url('/ar/admin'));
});

test('unsupported languages are rejected', function () {
    $this->put(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

test('pages are served in the language of the url with the matching direction', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['locale' => 'en'])
        ->get('/ar/settings/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'ar')
            ->where('direction', 'rtl')
            ->where('translations.Sign In', 'تسجيل الدخول'),
        );
});

test('unsupported language segments are not found', function () {
    $this->actingAs(User::factory()->create())
        ->get('/fr/settings/profile')
        ->assertNotFound();
});

test('links without a language are sent to the visitor language', function () {
    $this->withSession(['locale' => 'ar'])
        ->get('/settings/profile?tab=security')
        ->assertRedirect('/ar/settings/profile?tab=security');
});

test('the home page uses the account language when the session has none', function () {
    $this->actingAs(User::factory()->create(['locale' => 'ar']))
        ->get('/')
        ->assertRedirect('/ar');
});

test('the store default language is used before a visitor chooses one', function () {
    $settings = app(LocalizationSettings::class);
    $settings->default_locale = 'ar';
    $settings->save();

    $this->get('/')->assertRedirect('/ar');

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
});

test('unknown pages without a language are not found', function () {
    $this->get('/does-not-exist')->assertNotFound();
});
