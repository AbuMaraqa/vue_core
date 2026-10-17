<?php

use App\Modules\Core\Enums\FontFamily;
use App\Modules\Core\Enums\Permissions\SettingPermission;
use App\Modules\Core\Models\Site;
use App\Modules\Core\Models\TemporaryUpload;
use App\Modules\Core\Settings\AppearanceSettings;
use App\Modules\Core\Settings\GeneralSettings;
use App\Modules\Core\Settings\LocalizationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function validSettings(array $overrides = []): array
{
    return array_replace_recursive([
        'general' => [
            'site_name' => 'Motasem Store',
            'site_description' => 'Everything you need.',
            'contact_email' => 'hello@example.com',
            'contact_phone' => '+970599000000',
        ],
        'localization' => [
            'default_locale' => 'ar',
        ],
        'appearance' => [
            'font_family' => FontFamily::Cairo->value,
            'primary_color' => '#12B76A',
        ],
    ], $overrides);
}

test('staff members without the permission are forbidden', function () {
    $this->actingAs(staffMember())
        ->get(route('admin.settings.edit'))
        ->assertForbidden();
});

test('the settings screen shows the stored settings', function () {
    $this->actingAs(staffMember(SettingPermission::View))
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Edit')
            ->where('settings.general.site_name', config('app.name'))
            ->where('canUpdate', false)
            ->has('locales', 2),
        );
});

test('viewing settings does not allow changing them', function () {
    $this->actingAs(staffMember(SettingPermission::View))
        ->put(route('admin.settings.update'), validSettings())
        ->assertForbidden();
});

test('settings are saved', function () {
    $this->actingAs(staffMember(SettingPermission::View, SettingPermission::Update))
        ->put(route('admin.settings.update'), validSettings())
        ->assertRedirect(route('admin.settings.edit'));

    expect(app(GeneralSettings::class)->refresh())
        ->site_name->toBe('Motasem Store')
        ->contact_email->toBe('hello@example.com')
        ->and(app(LocalizationSettings::class)->refresh()->default_locale)->toBe('ar');
});

test('the chosen font and primary color are applied to every page', function () {
    $staffMember = staffMember(SettingPermission::View, SettingPermission::Update);

    $this->actingAs($staffMember)
        ->put(route('admin.settings.update'), validSettings())
        ->assertRedirect(route('admin.settings.edit'));

    expect(app(AppearanceSettings::class)->refresh())
        ->font_family->toBe(FontFamily::Cairo)
        ->primary_color->toBe('#12b76a');

    $this->actingAs($staffMember)
        ->get(route('admin.dashboard'))
        ->assertSee('--app-font: var(--font-cairo); --brand-color: #12b76a;', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('theme.font', 'cairo')
            ->where('theme.primaryColor', '#12b76a'),
        );
});

test('the appearance values are validated', function (array $appearance, string $field) {
    $this->actingAs(staffMember(SettingPermission::Update))
        ->put(route('admin.settings.update'), validSettings(['appearance' => $appearance]))
        ->assertSessionHasErrors($field);
})->with([
    'unknown font' => [['font_family' => 'comic-sans'], 'appearance.font_family'],
    'color name' => [['primary_color' => 'red'], 'appearance.primary_color'],
    'css injection' => [['primary_color' => '#fff; background: url(x)'], 'appearance.primary_color'],
]);

describe('logo', function () {
    beforeEach(function () {
        Storage::fake('local');
        Storage::fake('public');
    });

    test('an uploaded logo is attached and shared with every page', function () {
        $staffMember = staffMember(SettingPermission::View, SettingPermission::Update);
        $upload = TemporaryUpload::factory()->image('brand.png')->create(['user_id' => $staffMember->id]);

        $this->actingAs($staffMember)
            ->put(route('admin.settings.update'), validSettings(['logo' => ['uploads' => [$upload->id]]]))
            ->assertRedirect(route('admin.settings.edit'));

        $logo = Site::current()->getFirstMedia(Site::LOGO);

        expect($logo?->file_name)->toBe('brand.png');

        $this->actingAs($staffMember)
            ->get(route('admin.settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('theme.logoUrl', $logo->getFullUrl())
                ->where('logo.data.id', $logo->id),
            );
    });

    test('an image picked from the media library is copied as the logo', function () {
        $libraryImage = Site::current()->addMedia(UploadedFile::fake()->image('library.png'))->toMediaCollection(Site::LIBRARY);

        $this->actingAs(staffMember(SettingPermission::Update))
            ->put(route('admin.settings.update'), validSettings(['logo' => ['library' => [$libraryImage->id]]]))
            ->assertRedirect(route('admin.settings.edit'));

        $site = Site::current();

        expect($site->getFirstMedia(Site::LOGO)?->file_name)->toBe('library.png')
            ->and($site->getMedia(Site::LIBRARY))->toHaveCount(1);
    });

    test('the logo can be removed', function () {
        $logo = Site::current()->addMedia(UploadedFile::fake()->image('brand.png'))->toMediaCollection(Site::LOGO);

        $this->actingAs(staffMember(SettingPermission::Update))
            ->put(route('admin.settings.update'), validSettings(['logo' => ['removed' => [$logo->id]]]))
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertModelMissing($logo);
    });

    test('files that are not images are rejected', function () {
        $staffMember = staffMember(SettingPermission::Update);
        $upload = TemporaryUpload::factory()->create(['user_id' => $staffMember->id]);

        $this->actingAs($staffMember)
            ->put(route('admin.settings.update'), validSettings(['logo' => ['uploads' => [$upload->id]]]))
            ->assertSessionHasErrors('logo.uploads.0');
    });
});

test('the default language must be a supported locale', function () {
    $this->actingAs(staffMember(SettingPermission::Update))
        ->put(route('admin.settings.update'), validSettings(['localization' => ['default_locale' => 'fr']]))
        ->assertSessionHasErrors('localization.default_locale');
});
