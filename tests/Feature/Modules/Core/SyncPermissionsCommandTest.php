<?php

use App\Modules\Core\Enums\Permissions\UserPermission;
use App\Modules\Core\Support\Permissions\PermissionRegistry;
use Spatie\Permission\Models\Permission;

test('every registered permission is created', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->pluck('name')->all())
        ->toEqualCanonicalizing(app(PermissionRegistry::class)->names())
        ->toContain(UserPermission::Create->value);
});

test('stale permissions are kept unless pruning is requested', function () {
    Permission::create(['name' => 'legacy.permission', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')->assertSuccessful();
    expect(Permission::query()->where('name', 'legacy.permission')->exists())->toBeTrue();

    $this->artisan('permissions:sync', ['--prune' => true])->assertSuccessful();
    expect(Permission::query()->where('name', 'legacy.permission')->exists())->toBeFalse();
});
