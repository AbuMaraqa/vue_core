<?php

namespace App\Modules\Foundation\Support\ModuleGenerator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Write a new module with one fully working admin resource: model, migration,
 * factory, policy, permissions, requests, data, action, resource, controller,
 * export, routes, sidebar entry, Vue pages, TypeScript type and tests. The
 * result follows the same conventions as the Core and Foundation modules.
 *
 * The stubs live in app/Modules/Foundation/Console/stubs/module and use
 * `%%Placeholder%%` tokens, which never clash with PHP or Vue syntax.
 */
class ModuleGenerator
{
    /**
     * Stub name => target path, relative to the project root.
     *
     * @var array<string, string>
     */
    protected const array FILES = [
        'provider' => 'app/Modules/%%Module%%/Providers/%%Module%%ServiceProvider.php',
        'routes-admin' => 'app/Modules/%%Module%%/routes/admin.php',
        'model' => 'app/Modules/%%Module%%/Models/%%Model%%.php',
        'migration' => 'database/migrations/%%migrationDate%%_create_%%table%%_table.php',
        'factory' => 'database/factories/%%Model%%Factory.php',
        'policy' => 'app/Modules/%%Module%%/Policies/%%Model%%Policy.php',
        'store-request' => 'app/Modules/%%Module%%/Http/Requests/%%Models%%/Store%%Model%%Request.php',
        'update-request' => 'app/Modules/%%Module%%/Http/Requests/%%Models%%/Update%%Model%%Request.php',
        'data' => 'app/Modules/%%Module%%/Data/%%Model%%Data.php',
        'save-action' => 'app/Modules/%%Module%%/Actions/%%Models%%/Save%%Model%%.php',
        'resource' => 'app/Modules/%%Module%%/Http/Resources/%%Model%%Resource.php',
        'controller' => 'app/Modules/%%Module%%/Http/Controllers/%%Model%%Controller.php',
        'exporter' => 'app/Modules/%%Module%%/Exports/%%Models%%Exporter.php',
        'page-index' => 'resources/js/pages/admin/%%route%%/Index.vue',
        'page-create' => 'resources/js/pages/admin/%%route%%/Create.vue',
        'page-edit' => 'resources/js/pages/admin/%%route%%/Edit.vue',
        'form' => 'resources/js/components/%%route%%/%%Model%%Form.vue',
        'types' => 'resources/js/types/%%moduleKey%%.ts',
        'test' => 'tests/Feature/Modules/%%Module%%/%%Model%%ControllerTest.php',
    ];

    /**
     * The permissions every generated resource gets.
     *
     * @var list<string>
     */
    protected const array ACTIONS = ['View List', 'Create', 'Update', 'Delete', 'Export'];

    public function __construct(
        protected Filesystem $files,
        protected string $basePath,
        protected string $stubPath,
    ) {}

    /**
     * @param  string  $module  e.g. `Catalog`
     * @param  string|null  $resource  The model, e.g. `Category`; defaults to the singular module name.
     */
    public function generate(string $module, ?string $resource = null): GeneratedModule
    {
        $replacements = $this->replacements($module, $resource);

        $this->ensureModuleIsNew($replacements);

        $created = [];

        foreach (self::FILES as $stub => $target) {
            $path = $this->fill($target, $replacements);

            $this->files->ensureDirectoryExists(dirname($this->path($path)));
            $this->files->put($this->path($path), $this->fill($this->files->get("{$this->stubPath}/{$stub}.stub"), $replacements));

            $created[] = $path;
        }

        $updated = [
            $this->registerProvider($replacements),
            $this->addPermissions($replacements),
            $this->exportTypes($replacements),
        ];

        return new GeneratedModule(
            module: $replacements['Module'],
            model: $replacements['Model'],
            createdFiles: $created,
            updatedFiles: $updated,
            missingTranslations: $this->missingTranslations($replacements),
        );
    }

    /**
     * Every name the stubs need, derived from the module and model names.
     *
     * @return array<string, string>
     */
    public function replacements(string $module, ?string $resource = null): array
    {
        $module = Str::studly($module);
        $model = Str::studly($resource ?: Str::singular($module));

        foreach (['module' => $module, 'resource' => $model] as $kind => $name) {
            if (preg_match('/^[A-Z][A-Za-z]*$/', $name) !== 1) {
                throw new InvalidArgumentException("The {$kind} name [{$name}] must contain letters only, e.g. Catalog or BlogPost.");
            }
        }

        $models = Str::pluralStudly($model);

        return [
            'Module' => $module,
            'ModuleLabel' => Str::headline($module),
            'moduleKey' => Str::kebab($module),
            'Model' => $model,
            'Models' => $models,
            'modelVar' => Str::camel($model),
            'modelsVar' => Str::camel($models),
            'modelKebab' => Str::kebab($model),
            'param' => Str::snake($model),
            'table' => Str::snake($models),
            'route' => Str::kebab($models),
            'Label' => Str::headline($model),
            'Labels' => Str::headline($models),
            'labelLower' => Str::lower(Str::headline($model)),
            'migrationDate' => now()->format('Y_m_d_His'),
        ];
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function ensureModuleIsNew(array $replacements): void
    {
        if ($this->files->isDirectory($this->path("app/Modules/{$replacements['Module']}"))) {
            throw new RuntimeException("The module [{$replacements['Module']}] already exists.");
        }

        foreach (self::FILES as $target) {
            $path = $this->fill($target, $replacements);

            if (! str_starts_with($path, 'database/migrations/') && $this->files->exists($this->path($path))) {
                throw new RuntimeException("[{$path}] already exists; choose another name.");
            }
        }
    }

    /**
     * Add the provider after the existing module providers in bootstrap/providers.php.
     *
     * @param  array<string, string>  $replacements
     */
    protected function registerProvider(array $replacements): string
    {
        $path = 'bootstrap/providers.php';
        $provider = "{$replacements['Module']}ServiceProvider";
        $class = "App\\Modules\\{$replacements['Module']}\\Providers\\{$provider}";

        $content = $this->files->get($this->path($path));
        $content = $this->insertBeforeLast($content, '];', "    {$provider}::class,\n");
        $content = $this->insertAfterLast($content, "\nuse ", "use {$class};\n");

        $this->files->put($this->path($path), $content);

        return $path;
    }

    /**
     * Append the permissions of the resource to the list in PermissionSeeder.
     *
     * @param  array<string, string>  $replacements
     */
    protected function addPermissions(array $replacements): string
    {
        $path = 'database/seeders/PermissionSeeder.php';
        $marker = "        ];\n\n        foreach (\$permissions as \$permission) {";
        $content = $this->files->get($this->path($path));

        if (! str_contains($content, $marker)) {
            throw new RuntimeException("Could not find the permission list in [{$path}]; add the permissions by hand.");
        }

        $entries = collect(self::ACTIONS)->map(fn (string $action): string => implode("\n", [
            '            [',
            "                'name' => '{$replacements['Label']} {$action}',",
            "                'guard_name' => 'web',",
            "                'module_name' => '{$replacements['Label']} Management',",
            '            ],',
        ]))->implode("\n");

        $block = "\n            // {$replacements['Labels']}\n{$entries}\n";

        $this->files->put($this->path($path), str_replace($marker, $block.$marker, $content));

        return $path;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function exportTypes(array $replacements): string
    {
        $path = 'resources/js/types/index.ts';
        $lines = array_filter(explode("\n", $this->files->get($this->path($path))));
        $lines[] = "export * from './{$replacements['moduleKey']}';";

        sort($lines);

        $this->files->put($this->path($path), implode("\n", array_unique($lines))."\n");

        return $path;
    }

    /**
     * The interface strings of the generated module that have no Arabic
     * translation yet.
     *
     * @param  array<string, string>  $replacements
     * @return list<string>
     */
    protected function missingTranslations(array $replacements): array
    {
        $label = $replacements['Label'];

        $keys = [
            $replacements['ModuleLabel'],
            $replacements['Labels'],
            "Add {$label}",
            "Edit {$label}",
            "Delete {$replacements['labelLower']}",
            "{$label} created.",
            "{$label} updated.",
            "{$label} deleted.",
            "{$label} Management",
            ...array_map(fn (string $action): string => "{$label} {$action}", self::ACTIONS),
        ];

        $translationsPath = $this->path('lang/ar.json');
        $existing = $this->files->exists($translationsPath)
            ? (array) json_decode($this->files->get($translationsPath), true)
            : [];

        return array_values(array_unique(array_filter($keys, fn (string $key): bool => ! array_key_exists($key, $existing))));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function fill(string $template, array $replacements): string
    {
        return str_replace(
            array_map(fn (string $key): string => "%%{$key}%%", array_keys($replacements)),
            array_values($replacements),
            $template,
        );
    }

    protected function path(string $relative): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    protected function insertBeforeLast(string $content, string $needle, string $insertion): string
    {
        $position = strrpos($content, $needle);

        return $position === false ? $content : substr_replace($content, $insertion, $position, 0);
    }

    protected function insertAfterLast(string $content, string $needle, string $line): string
    {
        $position = strrpos($content, $needle);

        if ($position === false) {
            return $content;
        }

        $endOfLine = strpos($content, "\n", $position + 1);

        return $endOfLine === false ? $content : substr_replace($content, $line, $endOfLine + 1, 0);
    }
}
