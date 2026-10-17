<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Report whether the services the application needs are reachable, for load
 * balancers and uptime monitors. Laravel's `/up` only proves PHP boots; this
 * also checks the database, the cache and the storage disk.
 *
 * Failure details go to the log, never to the response.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check('database', fn () => DB::select('select 1')),
            'cache' => $this->check('cache', function (): void {
                $key = 'health-check:'.Str::random(8);

                Cache::put($key, true, 10);
                throw_unless(Cache::pull($key), RuntimeException::class, 'The cache did not return the stored value.');
            }),
            'storage' => $this->check('storage', function (): void {
                $path = 'health-check/'.Str::random(8);

                Storage::disk('local')->put($path, 'ok');
                Storage::disk('local')->delete($path);
            }),
        ];

        $healthy = collect($checks)->every(fn (array $check): bool => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'failing',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array{status: 'ok'|'failing', duration_ms: float}
     */
    protected function check(string $name, Closure $probe): array
    {
        $startedAt = hrtime(true);

        try {
            $probe();
            $status = 'ok';
        } catch (Throwable $exception) {
            logger()->error("Health check [{$name}] failed.", ['exception' => $exception]);
            $status = 'failing';
        }

        return [
            'status' => $status,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
        ];
    }
}
