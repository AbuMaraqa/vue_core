---
name: motasem-settings-theme
description: 'Site settings and theming in this project: spatie/laravel-settings classes (GeneralSettings, LocalizationSettings, AppearanceSettings) and their settings migrations, the admin Settings page, the runtime brand color and font (FontFamily enum, --brand-color/--app-font CSS variables, color-mix brand palette), the logo, and light/dark appearance. Activate whenever adding or changing a setting, the settings page, colors, fonts, logo, branding or dark mode, or when the user mentions الإعدادات، الألوان، الخط، الشعار، settings, theme, branding.'
---

# Settings & Theme

## Settings classes (spatie/laravel-settings)

Settings classes live in `app/Modules/Core/Settings/`. Each one has a `group()` and typed public properties:

| Class                  | Group          | Properties                                                                                   |
| ---------------------- | -------------- | -------------------------------------------------------------------------------------------- |
| `GeneralSettings`      | `general`      | `site_name`, `?site_description`, `?contact_email`, `?contact_phone`                         |
| `LocalizationSettings` | `localization` | `default_locale` (fallback when the visitor/user has none)                                   |
| `AppearanceSettings`   | `appearance`   | `FontFamily $font_family`, `string $primary_color` (hex), plus `theme()` for the shared prop |

**Reading.** Inject the class (`public function edit(GeneralSettings $general)`) or use `app(GeneralSettings::class)->site_name`.

**Adding a setting:**

1. Add the typed property to the class.
2. Create a settings migration in `database/settings/` (`php artisan make:settings-migration AddXToGeneralSettings`) with `$this->migrator->add('general.x', default)`.
3. Run `php artisan migrate`.
4. Validate it in `UpdateSettingsRequest`:
    - rules use `group.key` names (`'general.x' => [...]`);
    - add an `attributes()` label wrapped in `__()`.
5. Expose it in `SettingController::edit()` and save it in `update()` with `$general->fill($request->validated('general'))->save()`.
6. Add the field to `pages/admin/settings/Edit.vue` (or a card component under `components/settings/`), then add translations.

**A new settings group:**

- create a new class with its own `group()`;
- add a settings migration;
- inject it in `SettingController`;
- send it as `settings.<group>`.

Use a new class when the settings are unrelated to the existing groups.

**Permissions:** `Setting View` opens the page. `Setting Update` saves it; without it the page shows read-only (`canUpdate` prop).

## Brand theme

The site owner picks a font and a primary color, and the whole UI is derived from them.

| Piece                                                          | Role                                                                                                                                                                                                       |
| -------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `FontFamily` enum (`ibm-plex-sans-arabic`, `tajawal`, `cairo`) | `label()`, `cssVariable()` → `--font-{value}`, `options()` for the select. Fonts load through `bunny()` in `vite.config.ts` with `preload: false`.                                                         |
| `AppearanceSettings::theme()`                                  | Shared as `theme` together with `logoUrl` from `Site::current()->logoUrl()`.                                                                                                                               |
| `app.blade.php`                                                | Prints `style="--app-font: var(--font-x); --brand-color: #hex"` on `<html>` for the first paint.                                                                                                           |
| `useBrandTheme.ts`                                             | `applyTheme()` updates both variables on every Inertia navigation (`initializeBrandTheme()` in `app.ts`). The settings page also calls it to preview changes live and restores the saved theme on unmount. |
| `resources/css/app.css` `@theme`                               | `--color-brand-25…950` are computed with `color-mix(in oklab, var(--brand-color) N%, white/black)`. Use `brand-*` classes and the palette follows the setting.                                             |

- **Adding a font:** add an enum case and its label, plus a `bunny()` entry in `vite.config.ts`. Make sure the CSS variable `--font-<value>` exists (the Laravel Vite fonts plugin generates it).
- **Validation:** the color must match `/^#[0-9a-fA-F]{6}$/`, which also prevents CSS injection; tests cover it. It is stored lowercase.

## Logo

- The logo lives in the `Site::LOGO` media collection (single file).
- It is edited in the Appearance card of the settings page with `MediaUpload` (2 MB, images only), validated with `mediaRules('logo', ...)`, and saved with `SyncMediaCollection`. See **motasem-media**.
- Layouts show `page.props.theme.logoUrl`, falling back to `AppLogoIcon`.

## Light / dark mode (per visitor, not a setting)

- `useAppearance()` handles `light | dark | system` (`ThemeToggler.vue`).
- The choice is stored in localStorage plus the unencrypted `appearance` cookie, so `HandleAppearance` and `app.blade.php` render the right class before the JS loads.
- Dark styles use the `.dark` class variant (`@custom-variant dark`).
