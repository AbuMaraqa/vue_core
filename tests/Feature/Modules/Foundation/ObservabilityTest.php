<?php

use App\Modules\Foundation\Http\Middleware\AssignRequestId;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

describe('request id', function () {
    test('every response carries a request id that is also in the context', function () {
        $response = $this->get(route('home'));

        $requestId = $response->headers->get(AssignRequestId::HEADER);

        expect($requestId)->toMatch('/^[0-9a-f-]{36}$/')
            ->and(Context::get(AssignRequestId::CONTEXT_KEY))->toBe($requestId);
    });

    test('a safe incoming request id is kept and an unsafe one is replaced', function (string $incoming, bool $kept) {
        $response = $this->get(route('home'), [AssignRequestId::HEADER => $incoming]);

        expect($response->headers->get(AssignRequestId::HEADER) === $incoming)->toBe($kept);
    })->with([
        'load balancer id' => ['lb-3f2a9c1d-0001', true],
        'log injection' => ["abc\nfake log line", false],
        'too short' => ['abc', false],
    ]);
});

test('security headers are added to every response', function () {
    $this->get(route('home'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('hsts is only sent over https when enabled', function () {
    config(['foundation.security.hsts.enabled' => true]);

    $this->get('https://localhost/en')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

describe('health', function () {
    test('it reports every service as ok', function () {
        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.storage.status', 'ok');
    });

    test('it fails with 503 without leaking the error when a service is down', function () {
        DB::shouldReceive('select')->andThrow(new RuntimeException('SQLSTATE secret connection string'));

        $this->getJson(route('health'))
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'failing')
            ->assertJsonPath('checks.database.status', 'failing')
            ->assertDontSee('secret connection string');
    });
});

describe('error pages', function () {
    test('a missing page renders the translated error page', function () {
        $this->actingAs(staffMember())
            ->get('/en/admin/this-page-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/Error')
                ->where('status', 404)
                ->has('translations'),
            );
    });

    test('a forbidden page renders the error page', function () {
        $this->actingAs(staffMember())
            ->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('errors/Error')->where('status', 403));
    });

    test('a server error renders the error page with the request id when debug is off', function () {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/en/test-server-error', fn () => throw new RuntimeException('Boom'));

        $response = $this->get('/en/test-server-error');

        $response->assertInternalServerError()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/Error')
                ->where('status', 500)
                ->where('requestId', $response->headers->get(AssignRequestId::HEADER)),
            );
    });

    test('an expired page sends the user back with a toast', function () {
        Route::middleware('web')->post('/en/test-expired', fn () => throw new TokenMismatchException);

        $this->from('/en/settings/profile')
            ->post('/en/test-expired')
            ->assertRedirect('/en/settings/profile')
            ->assertInertiaFlash('toast.type', 'warning');
    });

    test('json errors keep their shape and gain the request id', function () {
        $response = $this->actingAs(staffMember())->getJson(route('admin.users.index'));

        $response->assertForbidden()
            ->assertJsonStructure(['message', 'request_id'])
            ->assertJsonPath('request_id', $response->headers->get(AssignRequestId::HEADER));
    });
});
