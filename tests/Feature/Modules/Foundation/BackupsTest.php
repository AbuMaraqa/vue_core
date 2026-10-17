<?php

use App\Models\User;
use App\Modules\Foundation\Jobs\CreateBackup;
use App\Modules\Foundation\Notifications\BackupFailed;
use App\Modules\Foundation\Notifications\BackupSucceeded;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;

beforeEach(function () {
    Storage::fake('local');
    config(['backup.backup.destination.disks' => ['local'], 'backup.backup.name' => 'motasem']);
});

/**
 * Put a backup archive where spatie/laravel-backup writes them.
 */
function storeBackup(string $name = '2026-10-17-10-00-00.zip'): void
{
    Storage::disk('local')->put("motasem/{$name}", 'zip content');
}

test('the backups are listed without exposing their paths in the urls', function () {
    storeBackup('2026-10-16-10-00-00.zip');
    storeBackup('2026-10-17-10-00-00.zip');

    $this->actingAs(staffMember('Backup View List'))
        ->get(route('admin.backups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/backups/Index')
            ->has('backups', 2)
            ->where('backups.0.name', '2026-10-17-10-00-00.zip')
            ->where('backups.0.disk', 'local')
            ->where('backups.0.size', 11)
            ->where('backups.0.id', fn (string $id): bool => preg_match('/^[0-9a-f]{64}$/', $id) === 1)
            ->where('can', ['create' => false, 'download' => false, 'delete' => false]),
        );
});

test('creating a backup runs it in the background', function () {
    Queue::fake();

    $this->actingAs(staffMember('Backup View List', 'Backup Create'))
        ->post(route('admin.backups.store'), ['type' => 'database'])
        ->assertRedirect(route('admin.backups.index'));

    Queue::assertPushed(CreateBackup::class, fn (CreateBackup $job): bool => $job->databaseOnly);
});

test('the backup type is validated', function () {
    $this->actingAs(staffMember('Backup Create'))
        ->post(route('admin.backups.store'), ['type' => 'everything'])
        ->assertSessionHasErrors('type');
});

test('the job runs the backup command', function (bool $databaseOnly, array $options) {
    Artisan::shouldReceive('call')->once()->with('backup:run', $options);

    (new CreateBackup($databaseOnly))->handle();
})->with([
    'database only' => [true, ['--only-db' => true]],
    'database and files' => [false, []],
]);

test('a backup can be downloaded and deleted by id', function () {
    storeBackup();
    $staffMember = staffMember('Backup View List', 'Backup Download', 'Backup Delete');

    $id = $this->actingAs($staffMember)->get(route('admin.backups.index'))->inertiaProps('backups.0.id');

    $this->actingAs($staffMember)
        ->get(route('admin.backups.download', ['backup' => $id]))
        ->assertOk()
        ->assertDownload('2026-10-17-10-00-00.zip');

    $this->actingAs($staffMember)
        ->delete(route('admin.backups.destroy', ['backup' => $id]))
        ->assertRedirect(route('admin.backups.index'));

    Storage::disk('local')->assertMissing('motasem/2026-10-17-10-00-00.zip');
});

test('a path can never be used instead of a backup id', function () {
    Storage::disk('local')->put('secrets.txt', 'private');

    $this->actingAs(staffMember('Backup Download'))
        ->get(route('admin.backups.download', ['backup' => '../secrets.txt']))
        ->assertNotFound();
});

test('the staff who manage backups hear how each backup ended', function () {
    Notification::fake();
    $backupManager = User::factory()->withPermissions('Backup View List')->create();
    $editor = User::factory()->withPermissions('User View List')->create();

    event(new BackupWasSuccessful('local', 'motasem'));
    event(new BackupHasFailed(new Exception('Disk full'), 'local', 'motasem'));

    Notification::assertSentTo($backupManager, BackupSucceeded::class);
    Notification::assertSentTo($backupManager, BackupFailed::class);
    Notification::assertNothingSentTo($editor);
});
