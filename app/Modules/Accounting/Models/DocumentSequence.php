<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The next number of a document type in a year. Read through DocumentNumbers,
 * which locks the row so two documents never get the same number.
 *
 * @property int $id
 * @property string $type
 * @property int $year
 * @property int $next_number
 */
#[Fillable(['type', 'year', 'next_number'])]
class DocumentSequence extends Model
{
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_number' => 'integer',
        ];
    }
}
