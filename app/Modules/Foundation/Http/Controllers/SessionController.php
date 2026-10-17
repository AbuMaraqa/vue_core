<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Foundation\Http\Resources\AuthenticationLogResource;
use App\Modules\Foundation\Models\AuthenticationLog;
use App\Modules\Foundation\Support\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own browser sessions and recent sign-ins, so they can
 * spot and end a session they do not recognise.
 */
class SessionController extends Controller
{
    public function __construct(protected UserSessions $sessions) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/Sessions', [
            'available' => $this->sessions->isAvailable(),
            'sessions' => $this->sessions->for($request->user(), $request->session()->getId()),
            'recentLogins' => AuthenticationLogResource::collection(
                AuthenticationLog::query()->whereBelongsTo($request->user())->latest('id')->limit(10)->get(),
            ),
        ]);
    }

    public function destroy(Request $request, string $sessionKey): RedirectResponse
    {
        $revoked = $this->sessions->revoke($request->user(), $sessionKey, $request->session()->getId());

        abort_unless($revoked, 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The session was signed out.')]);

        return back();
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $this->sessions->revokeOthers($request->user(), $request->session()->getId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Every other session was signed out.')]);

        return back();
    }
}
