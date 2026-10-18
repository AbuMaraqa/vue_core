<?php

namespace App\Modules\Accounting\Support;

use Illuminate\Support\Facades\DB;

/**
 * SQL expressions over dates that differ between database engines.
 */
final class SqlDate
{
    /**
     * An expression giving `YYYY-MM` for the date column, e.g. for monthly totals.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function yearMonth(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlsrv' => "format({$column}, 'yyyy-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
