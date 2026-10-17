<?php

namespace App\Modules\Foundation\Exports;

use App\Modules\Foundation\Models\AuthenticationLog;
use App\Modules\Foundation\Support\UserAgent;

/**
 * @extends Exporter<AuthenticationLog>
 */
class LoginHistoryExporter extends Exporter
{
    public function title(): string
    {
        return 'Login History';
    }

    public function columns(): array
    {
        return [
            'Event' => fn (AuthenticationLog $log): string => $log->event->label(),
            'Account' => fn (AuthenticationLog $log): ?string => $log->user?->name,
            'Email' => fn (AuthenticationLog $log): ?string => $log->email,
            'IP address' => fn (AuthenticationLog $log): ?string => $log->ip_address,
            'Browser' => fn (AuthenticationLog $log): ?string => UserAgent::parse($log->user_agent)->browser,
            'Platform' => fn (AuthenticationLog $log): ?string => UserAgent::parse($log->user_agent)->platform,
            'Date' => fn (AuthenticationLog $log): string => $log->created_at->format('Y-m-d H:i'),
        ];
    }
}
