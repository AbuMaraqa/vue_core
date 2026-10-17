<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Permission names are plain strings, so a typo would silently deny everyone
 * but the super admin. This guard reads every place the code names a
 * permission and checks it against the PermissionSeeder list.
 */
test('every permission named in the code is defined in the PermissionSeeder', function () {
    $this->seed(PermissionSeeder::class);

    $defined = Permission::query()->pluck('name')->all();

    $patterns = [
        // PHP: $user->can('User Create'), Gate::authorize('Setting View'), ->can('Media View List')
        'php' => "/\b(?:can|cannot|authorize|allows|denies)\(\s*'([A-Z][^']*)'/",
        // routes: ->middleware('can:Dashboard View')
        'route' => "/'can:([^',]+)'/",
        // Vue: can('User Create'), canAny('User Create', 'User Update')
        'vue' => "/\bcan(?:Any)?\(\s*'([A-Z][^']*)'/",
    ];

    $referenced = collect([
        ...File::allFiles(app_path()),
        ...File::allFiles(resource_path('js/pages')),
        ...File::allFiles(resource_path('js/components')),
    ])
        ->flatMap(function (SplFileInfo $file) use ($patterns): array {
            preg_match_all('/'.implode('|', array_map(fn (string $pattern): string => trim($pattern, '/'), $patterns)).'/', $file->getContents(), $matches);

            return collect(array_slice($matches, 1))
                ->flatten()
                ->filter()
                ->map(fn (string $name): string => "{$name} ({$file->getRelativePathname()})")
                ->all();
        });

    expect($referenced)->not->toBeEmpty();

    $unknown = $referenced
        ->reject(fn (string $reference): bool => in_array(strstr($reference, ' (', true), $defined, true))
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});
