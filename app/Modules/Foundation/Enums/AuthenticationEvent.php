<?php

namespace App\Modules\Foundation\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * What happened in an entry of the login history.
 */
enum AuthenticationEvent: string
{
    use EnumOptions;

    case Login = 'login';
    case Logout = 'logout';
    case Failed = 'failed';
    case Lockout = 'lockout';

    public function label(): string
    {
        return match ($this) {
            self::Login => __('Signed in'),
            self::Logout => __('Signed out'),
            self::Failed => __('Failed attempt'),
            self::Lockout => __('Locked out'),
        };
    }

    /**
     * The badge color used to display the event.
     */
    public function color(): string
    {
        return match ($this) {
            self::Login => 'success',
            self::Logout => 'light',
            self::Failed => 'warning',
            self::Lockout => 'error',
        };
    }
}
