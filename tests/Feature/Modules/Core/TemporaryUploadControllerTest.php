<?php

use App\Modules\Core\Models\TemporaryUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('guests cannot upload files', function () {
    $this->post(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('logo.png')])
        ->assertRedirect(route('login'));
});

test('an uploaded file is stored temporarily for the current user', function () {
    $staffMember = staffMember();

    $response = $this->actingAs($staffMember)
        ->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->image('logo.png')])
        ->assertCreated();

    $upload = TemporaryUpload::query()->findOrFail($response->json('id'));

    expect($upload)
        ->user_id->toBe($staffMember->id)
        ->original_name->toBe('logo.png')
        ->mime_type->toBe('image/png');

    Storage::disk('local')->assertExists($upload->path);
});

test('files larger than the media library limit are rejected', function () {
    config(['media-library.max_file_size' => 1024 * 100]);

    $this->actingAs(staffMember())
        ->postJson(route('admin.uploads.store'), ['file' => UploadedFile::fake()->create('big.pdf', 200)])
        ->assertJsonValidationErrors('file');
});

test('users can revert their own uploads', function () {
    $staffMember = staffMember();
    $upload = TemporaryUpload::factory()->image()->create(['user_id' => $staffMember->id]);

    $this->actingAs($staffMember)
        ->deleteJson(route('admin.uploads.destroy', $upload))
        ->assertNoContent();

    $this->assertModelMissing($upload);
    Storage::disk('local')->assertMissing($upload->path);
});

test('users cannot revert uploads of other users', function () {
    $upload = TemporaryUpload::factory()->image()->create();

    $this->actingAs(staffMember())
        ->deleteJson(route('admin.uploads.destroy', $upload))
        ->assertNotFound();

    $this->assertModelExists($upload);
});

test('uploads that were never attached are pruned with their files', function () {
    $staleUpload = TemporaryUpload::factory()->image()->create(['created_at' => now()->subDays(2)]);
    $recentUpload = TemporaryUpload::factory()->image()->create();

    $this->artisan('model:prune', ['--model' => [TemporaryUpload::class]])->assertSuccessful();

    $this->assertModelMissing($staleUpload);
    $this->assertModelExists($recentUpload);
    Storage::disk('local')->assertMissing($staleUpload->path);
});
