---
name: motasem-localization
description: 'How languages work in this project: the /{locale}/ URL segment, SetLocale middleware and its resolution order, the Locales service, LocaleController language switching, RTL direction, Laravel __() and the Vue t() helper backed by lang/ar.json, and adding a new language. Activate whenever adding or changing any user-visible string, translations, route URLs or redirects, language switching, RTL/LTR behaviour, or when the user mentions الترجمة، اللغة العربية، RTL، ar.json, locale or translation.'
---

# Localization (Arabic / English, RTL)

## Supported languages

- Supported languages are the entries **uncommented** in `config/laravellocalization.php` → `supportedLocales`. Currently `en` and `ar`.
- The direction comes from the locale's `script`. `Arab`, `Hebr`, ... give `rtl` (`Locales::RTL_SCRIPTS`).
- `App\Modules\Core\Support\Localization\Locales` is the only API to use:

| Method                   | Returns                              |
| ------------------------ | ------------------------------------ |
| `codes()`                | the supported locale codes           |
| `isSupported($x)`        | whether `$x` is one of them          |
| `apply($locale)`         | applies the locale                   |
| `direction($locale?)`    | `ltr` or `rtl`                       |
| `all()`                  | `{code, name, native, direction}[]`  |
| `translations($locale?)` | the contents of `lang/{locale}.json` |

## URLs carry the language

- **Localized pages:** all pages are under `/{locale}/...`. The admin panel is `/{locale}/admin`; the home and the user's own settings come from `routes/web.php` inside `Route::prefix('{locale}')`.
- **Pattern:** `CoreServiceProvider::configureLocalizedUrls()` restricts `{locale}` to supported codes (`Route::pattern`). It also sets `URL::defaults(['locale' => config('app.locale')])`, so `route()` works in console, queue and tests without passing a locale.
- **Not localized:**
    - Fortify routes (`/login`, `/register`, `/two-factor-challenge`, ...);
    - `PUT /locale` (`locale.update`);
    - `/.well-known/passkey-endpoints`.
- **Redirects:**
    - `/` and any unknown path without a language segment hit `LocalizedRedirectController`. It redirects to `/{current-locale}/{path}` only when that route exists, otherwise it returns 404.
    - Old links like `/admin` keep working through this.

Never add a `locale` parameter when generating URLs, in PHP (`route('admin.users.index')`) or Vue (Wayfinder `index()`).

## SetLocale middleware (`Core/Http/Middleware/SetLocale.php`)

1. If the route has a supported `{locale}`, store it in the session. Then **remove the parameter** (`forgetParameter`), so controllers never receive `$locale` in their arguments.
2. Resolve the locale in this order:
    1. session;
    2. `users.locale` of the signed-in user;
    3. `LocalizationSettings::$default_locale`;
    4. `config('app.locale')`.
3. Apply it through `Locales::apply`, and set `URL::defaults(['locale' => $locale])` for the rest of the request.

## Switching language

- **Vue:** `useLocale().switchLocale(code)` sends `PUT locale.update`.
- **`LocaleController`:**
    - validates the code;
    - stores it in the session and on the user (`users.locale`);
    - swaps the language segment of the previous URL (path and query only, so it never redirects off-site);
    - answers with `Inertia::location` for a full reload, because `lang` and `dir` change.
- **New users:** registration stores the current locale on the new user (`CreateNewUser`).

## Strings

- **Keys are the English source text.** A missing translation falls back to readable English, so there is no `en.json`.
- **PHP:** `__('User created.')`, with placeholders `__('Hello :name', ['name' => $x])`.
- **Vue:** `const { t } = useTranslations(); t('Showing :from to :to of :total results', { from, to, total })`.
    - All of `lang/{locale}.json` is shared as the `translations` prop.
    - Server-provided labels are translated on the client with `t(label)`: sidebar section/item titles, role names, permission names, `module_name`. So they must exist as keys too.
- **Arabic file:** `lang/ar.json` holds every Arabic string, with keys sorted alphabetically and UTF-8 Arabic text (not `\u` escapes).
- **Framework messages** live in `lang/{en,ar}/{auth,pagination,passwords,validation}.php`. Field names in validation errors come from FormRequest `attributes()` with `__()` labels.
- **Before finishing any UI change:** grep the new `t('...')`/`__('...')` keys and confirm each exists in `lang/ar.json`.

## Frontend sync

- `initializeLocale()` in `app.ts` makes Wayfinder fill `{locale}` from `document.documentElement.lang`.
- It also updates `<html lang dir>` after every Inertia navigation, from the `locale`/`direction` props.
- `app.blade.php` renders the initial `lang` and `dir`.
- Use RTL-safe Tailwind classes; see **motasem-admin-ui**.
- Format numbers and dates with `lib/format` and the current locale.

## API and notifications

- **API:** API requests choose their language with `Accept-Language` (`SetApiLocale`), and every API response carries `Content-Language`.
- **Notifications:** stored as English keys plus parameters, and translated when displayed, so each reader sees their own language (**motasem-foundation**).
- **Exports:** headings and PDF text follow the locale of the user who downloads them.

## Adding a language

1. Uncomment it in `config/laravellocalization.php` → `supportedLocales`.
2. Add `lang/<code>.json` (copy the keys of `ar.json`) and `lang/<code>/*.php`.
3. Nothing else changes: routes, the switcher and direction pick it up automatically.

## Tests

`tests/Feature/Modules/Core/LocaleControllerTest.php` shows the patterns:

```php
$this->actingAs($user)->from('/en/settings/profile?tab=1')
    ->put(route('locale.update'), ['locale' => 'ar'])
    ->assertRedirect(url('/ar/settings/profile?tab=1'))
    ->assertSessionHas('locale', 'ar');
```

For Inertia requests (`X-Inertia: true`), expect status 409 with an `X-Inertia-Location` header.
