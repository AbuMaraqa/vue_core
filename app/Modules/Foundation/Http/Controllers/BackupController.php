<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Foundation\Http\Requests\CreateBackupRequest;
use App\Modules\Foundation\Jobs\CreateBackup;
use App\Modules\Foundation\Support\Backups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Backup\BackupDestination\Backup;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Create, download and delete backups from the admin panel. Each action is
 * guarded by its own permission on the route.
 */
class BackupController extends Controller
{
    public function __construct(protected Backups $backups) {}

    public function index(): Response
    {
        return Inertia::render('admin/backups/Index', [
            'backups' => $this->backups->all(),
            'can' => [
                'create' => Gate::allows('Backup Create'),
                'download' => Gate::allows('Backup Download'),
                'delete' => Gate::allows('Backup Delete'),
            ],
        ]);
    }

    public function store(CreateBackupRequest $request): RedirectResponse
    {
        CreateBackup::dispatch($request->databaseOnly());

        Inertia::flash('toast', ['type' => 'info', 'message' => __('The backup has started. You will be notified when it is ready.')]);

        return to_route('admin.backups.index');
    }

    public function download(string $backup): StreamedResponse
    {
        $file = $this->findOrFail($backup);

        return $file->disk()->download($file->path());
    }

    public function destroy(string $backup): RedirectResponse
    {
        $this->findOrFail($backup)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup deleted.')]);

        return to_route('admin.backups.index');
    }

    protected function findOrFail(string $id): Backup
    {
        $backup = $this->backups->find($id);

        abort_if($backup === null, 404);

        return $backup;
    }
}
