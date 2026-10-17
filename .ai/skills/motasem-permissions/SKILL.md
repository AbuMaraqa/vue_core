---
name: motasem-permissions
description: "How authorization works in this project: the PermissionSeeder list, the '{Prefix} {Action}' permission naming, module_name grouping, policies built on AuthorizesWithPermissions, the Super Admin bypass, protected UserRole roles, and how permissions reach the sidebar and Vue. Activate whenever adding or renaming a permission, writing a policy or a Gate::authorize call, protecting a route, hiding a button by permission, touching roles/users management, or when the user mentions صلاحيات، أدوار، permissions, roles, policy, Super Admin, or a 403."
---

# Permissions & Roles

Built on `spatie/laravel-permission` with the `web` guard. Permissions are plain strings, defined once in a seeder. This mirrors the owner's `eccomerce` project and replaced an earlier enum-based design. Do not reintroduce permission enums or a registry unless asked.

## 1. Where permissions are defined

There is one source of truth: `database/seeders/PermissionSeeder.php`. Entries are grouped by a section comment per module:

```php
// Media
[
    'name' => 'Media View List',
    'guard_name' => 'web',
    'module_name' => 'Media Management',
],
```

What running the seeder does:

| Step | Effect                                                             |
| ---- | ------------------------------------------------------------------ |
| 1    | Upserts every entry, so `module_name` is updated on existing rows. |
| 2    | **Deletes** permissions missing from the list.                     |
| 3    | Creates every `UserRole` case as a role.                           |
| 4    | Syncs all permissions to the Super Admin role.                     |

Roles keep the permissions that still exist; nothing is wiped. Apply changes with `php artisan db:seed --class=PermissionSeeder`.

`module_name` is a column added by `2026_10_17_204255_add_module_name_to_permissions_table`. The roles screen groups the checkboxes by it (`RoleController::permissionGroups()`, ordered by id, so seeder order equals screen order).

## 2. Naming convention

The format is `"{Prefix} {Action}"`. The prefix is the human name of the resource:

| Prefix                       | Permissions                                          | Group name                       |
| ---------------------------- | ---------------------------------------------------- | -------------------------------- |
| `Dashboard`                  | `Dashboard View` (the gate of the whole admin panel) | Dashboard Management             |
| `User`                       | View List, Create, Update, Delete                    | User Management                  |
| `Roles And Permissions`      | View List, Create, Update, Delete                    | Roles And Permissions Management |
| `Media`                      | View List, Create, Delete                            | Media Management                 |
| `Setting`                    | View, Update                                         | Settings Management              |
| `Activity Log`               | View List                                            | Activity Log Management          |
| `User` (added by Foundation) | Export, Import                                       | User Management                  |
| `Login History`              | View List, Export                                    | Login History Management         |
| `Backup`                     | View List, Create, Download, Delete                  | Backup Management                |

Standard actions map to policy abilities:

| Action      | Policy ability |
| ----------- | -------------- |
| `View List` | `viewAny`      |
| `View`      | `view`         |
| `Create`    | `create`       |
| `Update`    | `update`       |
| `Delete`    | `delete`       |

Use other verbs only for real extra abilities, for example `Order Export`.

Every permission name and `module_name` is shown in the UI through `t()`. **Add an Arabic translation for each one to `lang/ar.json`.**

## 3. Policies

Model-backed resources get a policy in `app/Modules/<M>/Policies`. It uses the trait, which answers the five standard abilities from the prefix:

```php
class UserPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'User';
    }

    /**
     * Only super admins may manage other super admins.
     */
    public function update(User $user, User $model): bool
    {
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $this->hasPermission($user, 'Update');   // keep the permission check
    }
}
```

Register the policy in the module provider's `$policies` array. Override a method only to add business rules. Real examples:

- Nobody can delete themselves.
- Only a Super Admin manages Super Admins.
- The Super Admin role is never edited.
- Protected roles (`UserRole` cases) are never deleted or renamed.

Resources without a model (Settings, Activity Log, Dashboard) are checked by permission name directly.

## 4. Where to check

| Place                     | How                                                                                                                                                                 |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Whole admin area          | route middleware `can:Dashboard View` (`routes/admin.php`)                                                                                                          |
| Controller read actions   | `Gate::authorize('viewAny', Model::class)` / `Gate::authorize('update', $model)`, or `Gate::authorize('Setting View')` without a model                              |
| Write actions             | FormRequest `authorize()`: `$this->user()->can('create', Model::class)` or `->can('Setting Update')`                                                                |
| Delete actions            | `Gate::authorize('delete', $model)` in the controller                                                                                                               |
| Exports, imports, backups | `->can('Category Export')` on the **route**: the permission is checked before the FormRequest validates, so a forbidden user gets 403 instead of a validation error |
| Row buttons               | the Resource's `can` array: `'update' => $request->user()?->can('update', $this->resource) ?? false`                                                                |
| Page buttons              | `usePermissions().can('User Create')` in Vue, or a page prop such as `canUpload`                                                                                    |
| Sidebar                   | `SidebarItem::make('Users')->can('User View List')`. A group hides itself when no child is visible.                                                                 |
| After login               | `RedirectsToHome` sends holders of `Dashboard View` to `admin.dashboard`, everyone else to `home`                                                                   |

**Every new `admin.*` route must deny a staff member who lacks its permission.** `tests/Feature/Modules/Core/AdminRoutesAuthorizationTest.php` calls each admin route as a user holding only `Dashboard View` and expects 403. If the route has a model parameter, add a factory closure to its `$parameters` map. Only routes listed in `ROUTES_WITHOUT_PERMISSION` are exempt.

## 5. Roles and the Super Admin

`App\Modules\Core\Enums\UserRole` defines the system roles: `SuperAdmin = 'Super Admin'`, `Admin`, and `Customer`.

- **`UserRole::isProtected($name)`:** system roles can't be deleted or renamed. Their permissions can still be edited, except Super Admin's.
- **Super Admin bypass:** `Gate::after` in `CoreServiceProvider` grants every ability that returned `null`. An explicit `false` from a policy still wins.
    - Super Admins pass checks for permissions that are not seeded yet. **Always test new features with a non-super-admin user**, otherwise a typo in a permission string goes unnoticed.
- **Granting Super Admin:** only Super Admins may grant the role (`ValidatesAssignableRoles`) or see it in the assignable roles list (`UserController::assignableRoles()`).
- **`User::isSuperAdmin()`:** the check to use in PHP.

## 6. Checklist: adding permissions for a new resource

1. Add the entries to `PermissionSeeder` under a `// <Resource>` section comment, with a shared `module_name`.
2. Add Arabic translations for every name and the `module_name` to `lang/ar.json`.
3. Create the policy with `AuthorizesWithPermissions` and register it in the module provider.
4. Authorize every controller action and FormRequest. Add `can` flags to the Resource.
5. Gate the sidebar items with `->can(...)`.
6. Run `php artisan db:seed --class=PermissionSeeder`.
7. Write tests:
    - forbidden without the permission (`staffMember()`);
    - allowed with it (`staffMember('X Create')`);
    - make sure `AdminRoutesAuthorizationTest` still passes.

Permission strings are not type-checked, but `tests/Feature/Modules/Core/PermissionNamesTest.php` scans `app/` and the Vue pages/components for every `can(...)`, `Gate::authorize(...)` and `can:` middleware name and fails on any name missing from the seeder. Copy names exactly from the seeder.
