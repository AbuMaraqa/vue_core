<?php

namespace App\Modules\Foundation\Support\ModuleGenerator;

/**
 * What ModuleGenerator produced.
 */
final readonly class GeneratedModule
{
    /**
     * @param  list<string>  $createdFiles  Paths relative to the project root.
     * @param  list<string>  $updatedFiles  Existing files the module was registered in.
     * @param  list<string>  $missingTranslations  Keys to translate in lang/ar.json.
     */
    public function __construct(
        public string $module,
        public string $model,
        public array $createdFiles,
        public array $updatedFiles,
        public array $missingTranslations,
    ) {}
}
