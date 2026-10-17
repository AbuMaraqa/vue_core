<?php

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Enums\Permissions\MediaPermission;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(MediaPermission::ViewAny);
    }

    public function create(User $user): bool
    {
        return $user->can(MediaPermission::Create);
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->can(MediaPermission::Delete);
    }
}
