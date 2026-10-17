<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;

trait RedirectsToHome
{
    /**
     * Send staff members to the admin panel and everyone else to the home page.
     */
    protected function homePath(Request $request): string
    {
        return $request->user()?->can('Dashboard View')
            ? route('admin.dashboard', absolute: false)
            : route('home', absolute: false);
    }
}
