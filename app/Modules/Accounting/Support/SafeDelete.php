<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Exceptions\AccountingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Delete a record unless other records still point to it. The foreign keys
 * of the financial tables refuse such deletes; this turns the refusal into a
 * clear message instead of a server error.
 */
final class SafeDelete
{
    public static function delete(Model $model): void
    {
        try {
            $model->delete();
        } catch (Throwable $exception) {
            if (! $exception instanceof QueryException || ! str_starts_with((string) $exception->getCode(), '23')) {
                throw $exception;
            }

            throw new AccountingException(__('This record is used by other records and cannot be deleted. Deactivate it instead.'));
        }
    }
}
