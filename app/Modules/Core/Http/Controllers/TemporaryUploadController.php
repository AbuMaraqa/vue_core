<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Media\StoreTemporaryUploadRequest;
use App\Modules\Core\Models\TemporaryUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Receives the files the MediaUpload component sends while the user is still
 * filling in a form, mirroring the "process" and "revert" steps of FilePond.
 */
class TemporaryUploadController extends Controller
{
    public function store(StoreTemporaryUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');

        $upload = TemporaryUpload::create([
            'user_id' => $request->user()->id,
            'disk' => 'local',
            'path' => $file->storeAs('tmp/uploads/'.Str::uuid(), 'upload.'.$file->extension(), 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json(['id' => $upload->id], 201);
    }

    public function destroy(Request $request, TemporaryUpload $temporaryUpload): Response
    {
        abort_unless($temporaryUpload->user_id === $request->user()->id, 404);

        $temporaryUpload->delete();

        return response()->noContent();
    }
}
