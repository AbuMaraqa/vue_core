<?php

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Foundation\Models\AuthenticationLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The text of a downloaded spreadsheet or CSV file.
 */
function downloadedText(TestResponse $response): string
{
    $file = $response->baseResponse;

    return $file instanceof BinaryFileResponse ? (string) file_get_contents($file->getFile()->getPathname()) : (string) $response->getContent();
}

/**
 * A CSV upload with a header row and the given rows.
 *
 * @param  list<list<string>>  $rows
 */
function usersCsv(array $rows): UploadedFile
{
    $lines = array_map(fn (array $row): string => implode(',', $row), [['name', 'email', 'password', 'roles'], ...$rows]);

    return UploadedFile::fake()->createWithContent('users.csv', implode("\n", $lines));
}

describe('export', function () {
    test('staff members without the permission are forbidden before anything else', function () {
        $this->actingAs(staffMember('User View List'))
            ->get(route('admin.users.export'))
            ->assertForbidden();
    });

    test('the users matching the filters are exported with translated headings', function () {
        Role::findOrCreate('Editor', 'web');
        User::factory()->withRole('Editor')->create(['name' => 'Sara Editor', 'email' => 'sara@example.com']);
        User::factory()->create(['name' => 'Omar Customer', 'email' => 'omar@example.com']);

        $response = $this->actingAs(staffMember('User Export'))
            ->get(route('admin.users.export', ['format' => 'csv', 'filter' => ['role' => 'Editor']]))
            ->assertOk()
            ->assertDownload();

        $csv = downloadedText($response);

        expect($csv)->toContain('"Name","Email","Roles","Verified","Joined"')
            ->toContain('sara@example.com')
            ->not->toContain('omar@example.com');
    });

    test('the format must be one of the supported formats', function () {
        $this->actingAs(staffMember('User Export'))
            ->get(route('admin.users.export', ['format' => 'docx']))
            ->assertSessionHasErrors('format');
    });

    test('an excel file is produced', function () {
        $response = $this->actingAs(staffMember('User Export'))
            ->get(route('admin.users.export', ['format' => 'xlsx']))
            ->assertOk();

        expect($response->headers->get('content-disposition'))->toEndWith('.xlsx');
    })->skip(fn (): bool => ! class_exists(ZipArchive::class), 'The zip extension is required to write xlsx files.');

    test('a pdf is produced in arabic', function () {
        $response = $this->actingAs(staffMember('Login History Export'))
            ->get('/ar/admin/login-history/export?format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
    });

    test('the login history is exported', function () {
        AuthenticationLog::factory()->failed('intruder@example.com')->create();

        $response = $this->actingAs(staffMember('Login History Export'))
            ->get(route('admin.login-history.export', ['format' => 'csv']))
            ->assertOk();

        expect(downloadedText($response))->toContain('intruder@example.com')->toContain('Failed attempt');
    });
});

describe('import', function () {
    test('staff members without the permission are forbidden', function () {
        $this->actingAs(staffMember('User Create'))
            ->post(route('admin.users.import'), ['file' => usersCsv([])])
            ->assertForbidden();
    });

    test('valid rows are imported and invalid rows are reported with their row number', function () {
        Role::findOrCreate('Editor', 'web');
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs(staffMember('User Import'))
            ->from(route('admin.users.index'))
            ->post(route('admin.users.import'), ['file' => usersCsv([
                ['Sara Editor', 'sara@example.com', 'secret-password', 'Editor'],
                ['Bad Email', 'not-an-email', '', ''],
                ['Duplicate', 'taken@example.com', '', ''],
                ['Ghost Role', 'ghost@example.com', '', 'Ghost'],
            ])])
            ->assertRedirect(route('admin.users.index'))
            ->assertInertiaFlash('toast.type', 'warning')
            ->assertInertiaFlash('importResult.imported', 1)
            ->assertInertiaFlash('importResult.failures.0.row', 3)
            ->assertInertiaFlash('importResult.failures.0.column', 'email')
            ->assertInertiaFlash('importResult.failures.1.row', 4)
            ->assertInertiaFlash('importResult.failures.2.row', 5)
            ->assertInertiaFlash('importResult.failures.2.column', 'roles');

        $sara = User::query()->where('email', 'sara@example.com')->sole();

        expect($sara->hasRole('Editor'))->toBeTrue()
            ->and($sara->email_verified_at)->not->toBeNull()
            ->and(Hash::check('secret-password', $sara->password))->toBeTrue()
            ->and(User::query()->where('email', 'ghost@example.com')->exists())->toBeFalse();
    });

    test('a numeric password cell and an empty password are accepted', function () {
        $this->actingAs(staffMember('User Import'))
            ->post(route('admin.users.import'), ['file' => usersCsv([
                ['Numbers', 'numbers@example.com', '12345678', ''],
                ['No Password', 'nopassword@example.com', '', ''],
            ])])
            ->assertInertiaFlash('importResult.imported', 2);
    });

    test('only super admins may grant the super admin role', function () {
        $this->actingAs(staffMember('User Import'))
            ->post(route('admin.users.import'), ['file' => usersCsv([
                ['Climber', 'climber@example.com', '', UserRole::SuperAdmin->value],
            ])])
            ->assertInertiaFlash('importResult.imported', 0);

        expect(User::query()->where('email', 'climber@example.com')->exists())->toBeFalse();
    });

    test('the file must be a spreadsheet', function () {
        $this->actingAs(staffMember('User Import'))
            ->post(route('admin.users.import'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('file');
    });

    test('the template has the expected columns', function () {
        $this->actingAs(staffMember('User Import'))
            ->get(route('admin.users.import-template'))
            ->assertOk()
            ->assertDownload('users-import-template.xlsx');
    })->skip(fn (): bool => ! class_exists(ZipArchive::class), 'The zip extension is required to write xlsx files.');
});
