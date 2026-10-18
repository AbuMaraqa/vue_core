<?php

use App\Modules\Core\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * Admin routes any staff member may use without a dedicated permission:
 * the dashboard, temporary uploads, and the lookups every document form needs:
 * exchange rates, and the contact and item pickers (names and prices only).
 */
const ROUTES_WITHOUT_PERMISSION = [
    'admin.dashboard',
    'admin.uploads.store',
    'admin.uploads.destroy',
    'admin.accounting.exchange-rate',
    'admin.contacts.search',
    'admin.inventory.items.search',
];

/**
 * A value for a route parameter. Models bound by the controller are created
 * with their factory, so new modules are covered without editing this test.
 */
function routeParameterValue(RouteDefinition $route, string $parameter): mixed
{
    $models = [
        'role' => fn () => Role::findOrCreate('Editor', 'web'),
        'media' => fn () => Site::current()->addMedia(UploadedFile::fake()->image('photo.png'))->toMediaCollection(Site::LIBRARY),
    ];

    if (isset($models[$parameter])) {
        return $models[$parameter]();
    }

    foreach ($route->signatureParameters(['subClass' => Model::class]) as $signatureParameter) {
        $class = $signatureParameter->getType()?->getName();

        if ($signatureParameter->getName() === $parameter && is_string($class) && method_exists($class, 'factory')) {
            return $class::factory()->create();
        }
    }

    return 'placeholder';
}

test('every admin route requires its own permission', function () {
    Storage::fake('public');

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_starts_with((string) $route->getName(), 'admin.'))
        ->reject(fn (RouteDefinition $route): bool => in_array($route->getName(), ROUTES_WITHOUT_PERMISSION, true));

    expect($routes)->not->toBeEmpty();

    $staffMember = staffMember();
    $unprotected = [];

    foreach ($routes as $route) {
        $arguments = collect(array_diff($route->parameterNames(), ['locale']))
            ->mapWithKeys(fn (string $parameter): array => [$parameter => routeParameterValue($route, $parameter)])
            ->all();

        $method = $route->methods()[0];
        $status = $this->actingAs($staffMember)->call($method, route($route->getName(), $arguments))->getStatusCode();

        if ($status !== 403) {
            $unprotected[] = "{$method} {$route->getName()} returned {$status}";
        }
    }

    expect($unprotected)->toBe([]);
});
