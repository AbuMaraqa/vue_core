<?php

namespace App\Modules\Foundation\Console\Commands;

use App\Modules\Foundation\Support\ModuleGenerator\ModuleGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * php artisan make:module Catalog --resource=Category
 */
class MakeModuleCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'make:module
        {name : The module name, e.g. Catalog}
        {--resource= : The model the module manages, e.g. Category (defaults to the singular module name)}
        {--no-format : Do not run Pint, the frontend formatter and Wayfinder on the new files}';

    /**
     * @var string
     */
    protected $description = 'Create a module with a complete admin resource: model, permissions, CRUD pages, export and tests';

    public function handle(Filesystem $files): int
    {
        $generator = new ModuleGenerator($files, base_path(), dirname(__DIR__).'/stubs/module');

        try {
            $module = $generator->generate($this->argument('name'), $this->option('resource') ?: null);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("The {$module->module} module was created with the {$module->model} resource.");
        $this->components->bulletList([...$module->createdFiles, ...array_map(fn (string $path): string => "{$path} (updated)", $module->updatedFiles)]);

        if (! $this->option('no-format')) {
            $this->finish([...$module->createdFiles, ...$module->updatedFiles]);
        }

        $this->components->info('Next steps');
        $this->components->bulletList([
            'php artisan migrate',
            'php artisan db:seed --class=PermissionSeeder',
            "php artisan test --compact tests/Feature/Modules/{$module->module}",
            'Add the Arabic translations below to lang/ar.json',
        ]);

        if ($module->missingTranslations !== []) {
            $this->components->warn('Missing Arabic translations:');
            $this->components->bulletList($module->missingTranslations);
        }

        return self::SUCCESS;
    }

    /**
     * Format the new files the way the rest of the code base is formatted,
     * and generate the TypeScript route helpers of the new routes.
     *
     * @param  list<string>  $paths
     */
    protected function finish(array $paths): void
    {
        $php = array_values(array_filter($paths, fn (string $path): bool => str_ends_with($path, '.php')));
        $frontend = array_values(array_filter($paths, fn (string $path): bool => str_ends_with($path, '.vue') || str_ends_with($path, '.ts')));

        $steps = [
            'Formatting PHP' => [PHP_BINARY, 'vendor/bin/pint', ...$php],
            'Generating route helpers' => [PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form'],
            'Formatting Vue and TypeScript' => ['npx', 'vp', 'fmt', ...$frontend],
        ];

        foreach ($steps as $description => $command) {
            $this->components->task($description, function () use ($command, $description): bool {
                $result = Process::path(base_path())->timeout(300)->run($command);

                if ($result->failed()) {
                    $this->components->warn("{$description} failed: ".trim($result->errorOutput() ?: $result->output()));
                }

                return $result->successful();
            });
        }
    }
}
