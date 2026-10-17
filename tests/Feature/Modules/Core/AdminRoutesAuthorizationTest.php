<?php

use App\Models\User;
use App\Modules\Core\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * Admin routes any staff member may use without a dedicated permission.
 */
const ROUTES_WITHOUT_PERMISSION = [
    'admin.dashboard',
    'admin.uploads.store',
    'admin.uploads.destroy',
];

test('every admin route requires its own permission', function () {
    Storage::fake('public');

    $parameters = [
        'user' => fn () => User::factory()->create(),
        'role' => fn () => Role::findOrCreate('Editor', 'web'),
        'media' => fn () => Site::current()->addMedia(UploadedFile::fake()->image('photo.png'))->toMediaCollection(Site::LIBRARY),
    ];

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_starts_with((string) $route->getName(), 'admin.'))
        ->reject(fn (RouteDefinition $route): bool => in_array($route->getName(), ROUTES_WITHOUT_PERMISSION, true));

    expect($routes)->not->toBeEmpty();

    $staffMember = staffMember();
    $unprotected = [];

    foreach ($routes as $route) {
        $arguments = [];

        foreach (array_diff($route->parameterNames(), ['locale']) as $parameter) {
            if (! isset($parameters[$parameter])) {
                $unprotected[] = "{$route->getName()}: add a value for the [{$parameter}] parameter to this test";

                continue 2;
            }

            $arguments[$parameter] = $parameters[$parameter]();
        }

        $method = $route->methods()[0];
        $status = $this->actingAs($staffMember)->call($method, route($route->getName(), $arguments))->getStatusCode();

        if ($status !== 403) {
            $unprotected[] = "{$method} {$route->getName()} returned {$status}";
        }
    }

    expect($unprotected)->toBe([]);
});
