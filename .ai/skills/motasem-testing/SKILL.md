---
name: motasem-testing
description: 'Pest testing conventions of this project: test locations, the staffMember() helper, UserFactory states (superAdmin, withRole, withPermissions), Inertia page assertions, deferred props, Storage fakes for media, the AdminRoutesAuthorizationTest guard, PermissionSeeder tests, and which commands to run. Activate whenever writing, fixing or running tests, after any backend behaviour change, or when the user mentions اختبارات، تست، Pest, tests, coverage, failing test.'
---

# Testing (Pest 5)

Also read the generic `testing-best-practices` skill. This skill covers what is specific to this repo.

## Layout

```
tests/Feature/Auth/*                  Fortify flows (login, register, 2FA, reset, verification)
tests/Feature/Settings/*              the user's own profile and security pages
tests/Feature/Modules/Core/*          one file per controller: <Name>ControllerTest.php
tests/Feature/Modules/Core/Policies/  policy rules
tests/Feature/Modules/Core/Support/   Sidebar and other support classes
tests/Feature/Database/               seeders (PermissionSeeder)
```

- Feature tests use `RefreshDatabase` (configured in `tests/Pest.php`).
- `Tests\TestCase` calls `withoutVite()`, so tests never need `npm run build`, even for a page added a minute ago.
- New modules go in `tests/Feature/Modules/<Module>/`.
- Create a file with `php artisan make:test --pest Modules/Catalog/CategoryControllerTest --no-interaction`.
- Group the tests of one controller action with `describe('index' | 'store' | 'update' | 'destroy', ...)`.

## Users and permissions

| Helper                                                       | Gives you                                                                         |
| ------------------------------------------------------------ | --------------------------------------------------------------------------------- |
| `staffMember('User Create', ...)` (global, `tests/Pest.php`) | A user who can open the admin panel (`Dashboard View`) plus the given permissions |
| `User::factory()->withPermissions('X')`                      | Direct permissions, created on the fly if missing                                 |
| `User::factory()->withRole('Editor')`                        | A role, created if missing                                                        |
| `User::factory()->superAdmin()`                              | Bypasses every check                                                              |
| `->unverified()`, `->withTwoFactor()`                        | Fortify states                                                                    |

- Use the **least privileged** user that should pass, and always test the forbidden case with `staffMember()` (no extra permission).
- Use `superAdmin()` only when testing Super Admin behaviour; it hides missing or typo'd permissions.
- Permissions don't need seeding in tests: the factory states create them. Only `tests/Feature/Database/PermissionSeederTest.php` seeds.

## Typical assertions

```php
$this->actingAs(staffMember('User View List'))
    ->get(route('admin.users.index', ['filter' => ['search' => 'Sara']]))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/Index')
        ->has('users.data', 1)
        ->where('users.data.0.name', 'Sara Editor')
        ->where('filters.search', 'Sara'));

$this->actingAs(staffMember('User Create'))
    ->post(route('admin.users.store'), [...])
    ->assertRedirect(route('admin.users.index'))
    ->assertSessionHasNoErrors();

// validation through datasets
->with(['missing name' => [['name' => ''], 'name'], ...]);

// deferred props
->missing('registrations')
->loadDeferredProps(fn (Assert $reload) => $reload->has('registrations', 12));
```

- `route()` needs no `locale`: `URL::defaults` fills it with `en`.
- Pass `from('/en/...')` when the code reads the previous URL.
- Activity log entries can be asserted with `Spatie\Activitylog\Models\Activity`.
- Media tests need `Storage::fake('local')` and `Storage::fake('public')`; see **motasem-media**.

## The permission guards

Two tests protect the permission system for every module automatically:

- `tests/Feature/Modules/Core/AdminRoutesAuthorizationTest.php` iterates **every** `admin.*` route as `staffMember()` and expects 403.
    - Route parameters resolve themselves: a model bound in the controller signature is created with its factory, and other parameters get a placeholder. Only models without a factory need an entry in `routeParameterValue()`.
    - Only routes any staff member may use belong in `ROUTES_WITHOUT_PERMISSION`.
- `tests/Feature/Modules/Core/PermissionNamesTest.php` fails when `app/` or the Vue pages/components name a permission (`can('X')`, `Gate::authorize('X')`, `'can:X'`) that `PermissionSeeder` does not define. The failure lists each unknown name with its file.

## Foundation-specific helpers

- **API:** `Sanctum::actingAs($user)` or `->withToken($plainTextToken)`; send `Accept-Language` to test translated errors.
- **CSV export:** read a `BinaryFileResponse` with `file_get_contents($response->baseResponse->getFile()->getPathname())` (see `DataTransferTest::downloadedText()`).
- **Imports:** `UploadedFile::fake()->createWithContent('users.csv', $csv)`, then assert with `->assertInertiaFlash('importResult.imported', 1)`.
- **Notifications:** `Notification::fake()` with `assertSentTo`, or send for real and read `notifications.index`.
- **Sessions:** `config(['session.driver' => 'database'])` and insert rows in `sessions`.
- **Password-confirmed pages:** `withSession(['auth.password_confirmed_at' => time()])`.

## Running

| Purpose         | Command                                                                        |
| --------------- | ------------------------------------------------------------------------------ |
| One file        | `php artisan test --compact tests/Feature/Modules/Core/UserControllerTest.php` |
| One test        | `php artisan test --compact --filter="a user is created"`                      |
| Static analysis | `vendor/bin/phpstan analyse` (level 7, must stay clean)                        |
| PHP format      | `vendor/bin/pint --dirty --format agent`                                       |
| Frontend        | `npm run types:check`                                                          |

Finally, ask the user to run the full suite: `php artisan test --compact`.

On Windows, a media test can fail intermittently when another process runs tests at the same time (shared `storage/framework/testing/disks`). Re-run the file alone before debugging.
