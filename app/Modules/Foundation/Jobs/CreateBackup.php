<?php

namespace App\Modules\Foundation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * Run a backup in the background. Only one backup runs at a time; the result
 * reaches the staff through the backup notifications.
 */
class CreateBackup implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * A full backup of a large site can take a while.
     */
    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  bool  $databaseOnly  Skip the files and back up the database only.
     */
    public function __construct(public bool $databaseOnly = true) {}

    public function handle(): void
    {
        Artisan::call('backup:run', array_filter([
            '--only-db' => $this->databaseOnly,
        ]));
    }
}
