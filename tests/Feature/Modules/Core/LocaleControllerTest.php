<?php

use App\Models\User;
use App\Modules\Core\Settings\LocalizationSettings;
use Inertia\Testing\AssertableInertia as Assert;

test('switching the language is remembered for the session and the account', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('locale', 'ar');

    expect($user->fresh()->locale)->toBe('ar');
});

test('unsupported languages are rejected', function () {
    $this->put(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

test('pages are served in the chosen language with the matching direction', function () {
    $this->withSession(['locale' => 'ar'])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'ar')
            ->where('direction', 'rtl')
            ->where('translations.Sign In', 'تسجيل الدخول'),
        );
});

test('the store default language is used before a visitor chooses one', function () {
    $settings = app(LocalizationSettings::class);
    $settings->default_locale = 'ar';
    $settings->save();

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
});

test('the account language is used when the session has none', function () {
    $this->actingAs(User::factory()->create(['locale' => 'ar']))
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'ar')
            ->where('direction', 'rtl'),
        );
});
