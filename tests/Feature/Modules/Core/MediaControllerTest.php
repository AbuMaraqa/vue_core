<?php

use App\Modules\Core\Models\Site;
use App\Modules\Core\Models\TemporaryUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('staff members without the permission are forbidden', function () {
    $this->actingAs(staffMember())
        ->get(route('admin.media.index'))
        ->assertForbidden();
});

test('the library lists the media of every model and can be filtered by type', function () {
    $site = Site::current();
    $site->addMedia(UploadedFile::fake()->image('banner.png'))->toMediaCollection(Site::LIBRARY);
    $site->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection(Site::LOGO);

    $this->actingAs(staffMember('Media View List'))
        ->get(route('admin.media.index', ['filter' => ['type' => 'image']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/media/Index')
            ->has('media.data', 2)
            ->where('media.data.0.name', 'logo')
            ->where('media.data.0.collection', Site::LOGO)
            ->where('media.data.0.is_image', true)
            ->where('canUpload', false),
        );
});

test('the picker only returns images', function () {
    $site = Site::current();
    $site->addMedia(UploadedFile::fake()->image('banner.png'))->toMediaCollection(Site::LIBRARY);
    $site->addMedia(UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'))->toMediaCollection('documents');

    $this->actingAs(staffMember('Media View List'))
        ->getJson(route('admin.media.browse'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.file_name', 'banner.png');
});

test('uploads are saved into the library', function () {
    $staffMember = staffMember('Media Create');
    $upload = TemporaryUpload::factory()->image('product.png')->create(['user_id' => $staffMember->id]);

    $this->actingAs($staffMember)
        ->post(route('admin.media.store'), ['uploads' => [$upload->id]])
        ->assertRedirect(route('admin.media.index'));

    $media = Site::current()->getFirstMedia(Site::LIBRARY);

    expect($media?->file_name)->toBe('product.png');
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    $this->assertModelMissing($upload);
});

test('uploads of other users cannot be saved', function () {
    $upload = TemporaryUpload::factory()->image()->create();

    $this->actingAs(staffMember('Media Create'))
        ->post(route('admin.media.store'), ['uploads' => [$upload->id]])
        ->assertSessionHasErrors('uploads.0');

    expect(Site::current()->getMedia(Site::LIBRARY))->toBeEmpty();
});

test('deleting media requires the permission', function () {
    $media = Site::current()->addMedia(UploadedFile::fake()->image('banner.png'))->toMediaCollection(Site::LIBRARY);

    $this->actingAs(staffMember('Media View List'))
        ->delete(route('admin.media.destroy', $media))
        ->assertForbidden();

    $this->actingAs(staffMember('Media Delete'))
        ->delete(route('admin.media.destroy', $media))
        ->assertRedirect();

    $this->assertModelMissing($media);
});
