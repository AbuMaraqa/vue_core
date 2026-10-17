<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Enums\FontFamily;
use App\Modules\Core\Enums\Permissions\SettingPermission;
use App\Modules\Core\Http\Requests\Settings\UpdateSettingsRequest;
use App\Modules\Core\Settings\AppearanceSettings;
use App\Modules\Core\Settings\GeneralSettings;
use App\Modules\Core\Settings\LocalizationSettings;
use App\Modules\Core\Support\Localization\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(GeneralSettings $general, LocalizationSettings $localization, AppearanceSettings $appearance, Locales $locales): Response
    {
        Gate::authorize(SettingPermission::View);

        return Inertia::render('admin/settings/Edit', [
            'settings' => [
                'general' => $general->toArray(),
                'localization' => $localization->toArray(),
                'appearance' => [
                    'font_family' => $appearance->font_family->value,
                    'primary_color' => $appearance->primary_color,
                ],
            ],
            'locales' => $locales->all(),
            'fonts' => FontFamily::options(),
            'canUpdate' => Gate::allows(SettingPermission::Update),
        ]);
    }

    public function update(UpdateSettingsRequest $request, GeneralSettings $general, LocalizationSettings $localization, AppearanceSettings $appearance): RedirectResponse
    {
        $general->fill($request->validated('general'))->save();
        $localization->fill($request->validated('localization'))->save();
        $appearance->fill([
            'font_family' => $request->enum('appearance.font_family', FontFamily::class),
            'primary_color' => strtolower($request->string('appearance.primary_color')->value()),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('admin.settings.edit');
    }
}
