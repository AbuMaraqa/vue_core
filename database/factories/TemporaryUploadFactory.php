<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Core\Models\TemporaryUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemporaryUpload>
 */
class TemporaryUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'tmp/uploads/'.Str::uuid().'/file.txt',
            'original_name' => 'file.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
        ];
    }

    /**
     * Store a real image on the upload disk, so it can be attached to a model.
     */
    public function image(string $name = 'photo.png', int $kilobytes = 20): static
    {
        return $this->afterMaking(function (TemporaryUpload $upload) use ($name, $kilobytes): void {
            $file = UploadedFile::fake()->image($name)->size($kilobytes);
            $directory = 'tmp/uploads/'.Str::uuid();

            $upload->forceFill([
                'path' => $file->storeAs($directory, $name, $upload->disk),
                'original_name' => $name,
                'mime_type' => 'image/png',
                'size' => $kilobytes * 1024,
            ]);
        });
    }
}
