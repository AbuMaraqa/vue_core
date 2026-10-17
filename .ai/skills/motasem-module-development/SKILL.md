---
name: motasem-module-development
description: 'Step-by-step recipe for adding a new module (app/Modules/<Name>) or a new admin CRUD feature in this project: provider, routes, permissions, policy, sidebar, migration/model/factory, FormRequests, Data, Actions, Resources, controller, Vue Index/Create/Edit pages, translations and tests. Activate whenever the user asks to build a new feature, section, page or module (موديول جديد، قسم جديد، صفحة جديدة، CRUD), e.g. categories, products, orders, branches, or to extend an existing admin resource.'
---

# Building a Module / Admin Feature

Follow the Core module as the reference implementation. **Users** is the most complete CRUD; copy its shape. Read **motasem-architecture** first. Read **motasem-permissions** and **motasem-admin-ui** for the details of those layers.

Example used below: a `Catalog` module that manages `Category`.

## 0. Generate it

For a new module, start with the generator. It produces every layer below, already wired, formatted and tested:

```bash
php artisan make:module Catalog --resource=Category
```

Then adapt the generated model, migration, rules, resource, form and tests to the real fields; the sections below explain each layer. To add a second resource to an existing module, copy the generated files of the first one. See **motasem-foundation**.

## 0.1 Decide: new module or Core?

- **New business domain** (catalog, orders, branches...): create `app/Modules/<Name>`.
- **Admin infrastructure** (users, roles, media, settings, logs): it belongs in `Core`.
- **Shared code:**
    - Base classes and support live in Core (`ModuleServiceProvider`, `Sidebar`, `AuthorizesWithPermissions`, `ValidatesMediaInput`, `SyncMediaCollection`).
    - Other modules depend on Core. Core never depends on them.

## 1. Module skeleton

Only create the folders you need:

```
app/Modules/Catalog/
  Actions/Categories/SaveCategory.php
  Data/CategoryData.php
  Http/Controllers/CategoryController.php
  Http/Requests/Categories/StoreCategoryRequest.php, UpdateCategoryRequest.php
  Http/Resources/CategoryResource.php
  Models/Category.php
  Policies/CategoryPolicy.php
  Providers/CatalogServiceProvider.php
  routes/admin.php
```

- Migrations, factories and seeders stay in the global `database/` folders. Models point to their factory with `#[UseFactory(CategoryFactory::class)]` (see `Core/Models/Site.php`).
- Create classes with artisan where possible, then move them into the module namespace: `php artisan make:migration create_categories_table --no-interaction`, `php artisan make:factory CategoryFactory --no-interaction`.

## 2. Provider + registration

```php
namespace App\Modules\Catalog\Providers;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Category::class => CategoryPolicy::class,
    ];

    protected array $routes = [
        'routes/admin.php',
    ];

    protected function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    protected function sidebar(Sidebar $sidebar): void
    {
        $sidebar->section('catalog', 'Catalog', sort: 20)->add(
            SidebarItem::make('Categories')
                ->icon('tags')
                ->route('admin.categories.index')
                ->activeWhen('admin.categories.*')
                ->can('Category View List')
                ->sort(10),
        );
    }
}
```

- Add the provider to `bootstrap/providers.php`, after `CoreServiceProvider`.
- Existing sidebar sections: `main` ("Menu", sort 10) and `system` ("System", sort 90). Pass the same key to add items to an existing section. Use `SidebarGroup::make('...')->icon(...)->add(SidebarItem ...)` for a collapsible menu.
- The icon name must exist in `resources/js/components/common/AppIcon.vue`. Register new Solar icons there.
- If you override `boot()`, call `parent::boot()`.

## 3. Routes (`routes/admin.php` of the module)

Copy the Core admin group exactly, so locale, auth and the admin gate apply:

```php
Route::middleware(['web', 'auth', 'verified', 'can:Dashboard View'])
    ->prefix('{locale}/admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('categories', CategoryController::class)->except('show');
    });
```

Then regenerate the TS route helpers. The Vite plugin does it in dev; otherwise run `php artisan wayfinder:generate`. Import them in Vue from `@/routes/admin/categories`.

## 4. Permissions + policy

Follow the checklist in **motasem-permissions**:

- seeder entries `Category View List|Create|Update|Delete` with `module_name` `Category Management`;
- Arabic translations;
- `CategoryPolicy` using `AuthorizesWithPermissions` with prefix `Category`;
- `php artisan db:seed --class=PermissionSeeder`.

## 5. Backend layers (copy the Users feature)

| Layer        | Reference file                                            | Rule                                                                                                                                                                                                                            |
| ------------ | --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FormRequest  | `Core/Http/Requests/Users/StoreUserRequest.php`           | `authorize()` calls the policy. `rules()` returns typed arrays. Add `attributes()` with `__()` labels for nicer errors. The Update request resolves the route model via a small helper that `abort_unless(... instanceof ...)`. |
| Data         | `Core/Data/UserData.php`                                  | `final`-style DTO extending `Spatie\LaravelData\Data`, promoted public props, PHPDoc for array item types.                                                                                                                      |
| Action       | `Core/Actions/Users/CreateUser.php`, `Roles/SaveRole.php` | One public `handle()`, wrapped in `DB::transaction` when it touches several tables. A single `Save*` action may handle both create and update (`$model->exists`).                                                               |
| Resource     | `Core/Http/Resources/UserResource.php`                    | Explicit fields, ISO dates (`?->toIso8601String()`), relations via `whenLoaded`, and a `can` array with `update`/`delete` from the policy.                                                                                      |
| Controller   | `Core/Http/Controllers/UserController.php`                | See the shape below.                                                                                                                                                                                                            |
| Activity log | `User::getActivitylogOptions()`                           | Add `LogsActivity` to the model with `useLogName('<plural>')->logOnly([...])->logOnlyDirty()->dontLogEmptyChanges()`.                                                                                                           |

Controller shape:

```php
public function index(Request $request): Response
{
    Gate::authorize('viewAny', Category::class);

    $categories = QueryBuilder::for(Category::query())
        ->allowedFilters(
            AllowedFilter::callback('search', fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))->delimiter(''),
        )
        ->allowedSorts('name', 'created_at')
        ->defaultSort('-created_at')
        ->paginate(15)
        ->withQueryString();

    return Inertia::render('admin/categories/Index', [
        'categories' => CategoryResource::collection($categories),
        'filters' => [
            'search' => $request->input('filter.search'),
            'sort' => $request->input('sort', '-created_at'),
        ],
    ]);
}

public function store(StoreCategoryRequest $request, SaveCategory $saveCategory): RedirectResponse
{
    $saveCategory->handle(new Category, CategoryData::from($request->validated()));

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

    return to_route('admin.categories.index');
}
```

- `create`/`edit` call `Gate::authorize` and render `admin/categories/Create|Edit` with `CategoryResource::make($category)` plus option lists.
- `destroy` calls `Gate::authorize('delete', $category)`, deletes, flashes a toast and redirects to the index.
- For image fields, follow **motasem-media**: `InteractsWithMedia`, the `ValidatesMediaInput` trait, and `SyncMediaCollection`.

## 6. Frontend

Follow **motasem-admin-ui**. Copy `pages/admin/users/*` and `components/users/UserForm.vue`:

- `pages/admin/categories/Index.vue`: PageHeader with breadcrumbs and a create button, a filter bar, a table with `th-cell`/`td-cell`, row actions from `item.can`, `EmptyState`, `TablePagination` and a `ConfirmDialog` for delete.
- `Create.vue` / `Edit.vue`: PageHeader plus `<CategoryForm :category="category?.data" />`.
- `components/categories/CategoryForm.vue`: `useForm`, then `form.submit(category ? update({category: id}) : store())`.
- Add a TS type for the resource in `resources/js/types/` (export it from `types/index.ts`). Mirror the Resource exactly.
- The page layout is automatic: every page outside `auth/` and `Welcome` gets `AdminLayout`.

## 7. Translations

Add every new `t()` / `__()` string to `lang/ar.json`, including:

- sidebar titles and section titles;
- permission names and `module_name`;
- toast messages;
- validation attribute labels.

## 8. Tests (Pest, `tests/Feature/Modules/<Module>/`)

Follow **motasem-testing**. At minimum:

- index forbidden without the permission, and lists/filters with it;
- store creates and validates;
- update and destroy, including the policy rules;
- `AdminRoutesAuthorizationTest` passes. Add `'category' => fn () => Category::factory()->create()` to its `$parameters`.

## 9. Finish

1. Run `vendor/bin/pint --dirty --format agent`.
2. Run the module's tests, then `php artisan test --compact tests/Feature/Modules/Core/AdminRoutesAuthorizationTest.php`.
3. Run `npm run types:check`.
4. Ask the user to run the full suite.
