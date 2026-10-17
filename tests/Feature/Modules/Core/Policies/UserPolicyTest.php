<?php

use App\Models\User;

test('permission holders may manage regular users', function (string $ability, string $permission) {
    $actor = User::factory()->withPermissions($permission)->create();

    expect($actor->can($ability, User::factory()->create()))->toBeTrue()
        ->and(User::factory()->create()->can($ability, User::factory()->create()))->toBeFalse();
})->with([
    'update' => ['update', 'User Update'],
    'delete' => ['delete', 'User Delete'],
]);

test('only super admins may manage super admins', function (string $ability, string $permission) {
    $superAdmin = User::factory()->superAdmin()->create();

    expect(User::factory()->withPermissions($permission)->create()->can($ability, $superAdmin))->toBeFalse()
        ->and(User::factory()->superAdmin()->create()->can($ability, $superAdmin))->toBeTrue();
})->with([
    'update' => ['update', 'User Update'],
    'delete' => ['delete', 'User Delete'],
]);

test('nobody may delete themselves, not even a super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    expect($superAdmin->can('delete', $superAdmin))->toBeFalse();
});
