<?php

namespace App\Http\Responses\Concerns;

use App\Modules\Core\Enums\Permissions\DashboardPermission;
use Illuminate\Http\Request;

trait RedirectsToHome
{
    /**
     * Send staff members to the admin panel and everyone else to the home page.
     */
    protected function homePath(Request $request): string
    {
        return $request->user()?->can(DashboardPermission::Access)
            ? route('admin.dashboard', absolute: false)
            : route('home', absolute: false);
    }
}
