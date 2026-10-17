<?php

namespace App\Modules\Foundation\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The free-text `filter[search]` of an index page, matched against several
 * columns at once:
 *
 * ```php
 * QueryBuilder::for(Category::class)->allowedFilters(SearchFilter::on('name', 'slug'));
 * ```
 *
 * Commas in the search text are kept as typed instead of splitting the value.
 */
final class SearchFilter
{
    public static function on(string ...$columns): AllowedFilter
    {
        return AllowedFilter::callback(
            'search',
            fn (Builder $query, string $search) => $query->whereAny($columns, 'like', "%{$search}%"),
        )->delimiter('');
    }
}
