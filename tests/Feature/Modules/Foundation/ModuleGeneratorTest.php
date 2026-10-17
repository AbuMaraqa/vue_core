<?php

use App\Modules\Foundation\Support\ModuleGenerator\ModuleGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

/**
 * The generator works on a copy of the files it registers the module in, so
 * the real project is never touched.
 */
beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'module-generator-'.uniqid();
    $this->files = new Filesystem;

    foreach (['bootstrap/providers.php', 'database/seeders/PermissionSeeder.php', 'resources/js/types/index.ts', 'lang/ar.json'] as $path) {
        $this->files->ensureDirectoryExists(dirname("{$this->root}/{$path}"));
        $this->files->copy(base_path($path), "{$this->root}/{$path}");
    }

    $this->generator = new ModuleGenerator($this->files, $this->root, app_path('Modules/Foundation/Console/stubs/module'));
});

afterEach(function () {
    $this->files->deleteDirectory($this->root);
});

test('a module is generated with every file and no placeholder left', function () {
    $module = $this->generator->generate('Inventory', 'StockItem');

    expect($module->createdFiles)->toContain(
        'app/Modules/Inventory/Providers/InventoryServiceProvider.php',
        'app/Modules/Inventory/Http/Controllers/StockItemController.php',
        'resources/js/pages/admin/stock-items/Index.vue',
        'resources/js/components/stock-items/StockItemForm.vue',
        'tests/Feature/Modules/Inventory/StockItemControllerTest.php',
    );

    foreach ($module->createdFiles as $path) {
        $content = $this->files->get("{$this->root}/{$path}");

        expect($content)->not->toContain('%%');

        if (str_ends_with($path, '.php')) {
            expect(Process::run([PHP_BINARY, '-l', "{$this->root}/{$path}"])->successful())->toBeTrue("{$path} is not valid PHP");
        }
    }

    expect($this->files->get("{$this->root}/app/Modules/Inventory/routes/admin.php"))
        ->toContain("Route::resource('stock-items', StockItemController::class)")
        ->and($this->files->get("{$this->root}/app/Modules/Inventory/Http/Requests/StockItems/UpdateStockItemRequest.php"))
        ->toContain("\$this->route('stock_item')");
});

test('the module is registered: provider, permissions and types', function () {
    $this->generator->generate('Inventory', 'StockItem');

    expect($this->files->get("{$this->root}/bootstrap/providers.php"))
        ->toContain('use App\Modules\Inventory\Providers\InventoryServiceProvider;')
        ->toContain('    InventoryServiceProvider::class,')
        ->and($this->files->get("{$this->root}/database/seeders/PermissionSeeder.php"))
        ->toContain("'name' => 'Stock Item View List',")
        ->toContain("'name' => 'Stock Item Export',")
        ->toContain("'module_name' => 'Stock Item Management',")
        ->and($this->files->get("{$this->root}/resources/js/types/index.ts"))
        ->toContain("export * from './inventory';");

    expect(Process::run([PHP_BINARY, '-l', "{$this->root}/database/seeders/PermissionSeeder.php"])->successful())->toBeTrue();
});

test('the strings that still need an arabic translation are listed', function () {
    $module = $this->generator->generate('Inventory', 'StockItem');

    expect($module->missingTranslations)
        ->toContain('Stock Items', 'Add Stock Item', 'Stock Item View List')
        ->not->toContain('Name');
});

test('the resource defaults to the singular of the module name', function () {
    expect($this->generator->replacements('Branches'))
        ->Model->toBe('Branch')
        ->route->toBe('branches');
});

test('an existing module is never overwritten', function () {
    $this->generator->generate('Inventory', 'StockItem');

    $this->generator->generate('Inventory', 'Warehouse');
})->throws(RuntimeException::class, 'The module [Inventory] already exists.');

test('names with anything but letters are refused', function () {
    $this->generator->generate('Inventory', 'Stock-Item 2');
})->throws(InvalidArgumentException::class);
