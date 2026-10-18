<?php

namespace App\Modules\Accounting\Exceptions;

use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A rule of the books was broken, e.g. an unbalanced entry or a locked date.
 * The message is already translated and is shown to the user as is: a toast
 * and a form error on pages, a 422 response for API calls. It is not logged,
 * because it is not a fault of the application.
 */
class AccountingException extends RuntimeException
{
    public function report(): void
    {
        //
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => ['accounting' => [$this->getMessage()]],
            ], 422);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back()->withErrors(['accounting' => $this->getMessage()]);
    }
}
