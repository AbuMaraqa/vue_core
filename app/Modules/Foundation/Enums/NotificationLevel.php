<?php

namespace App\Modules\Foundation\Enums;

/**
 * How important a notification is; it decides its color in the interface.
 */
enum NotificationLevel: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
