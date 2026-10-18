<?php

namespace App\Modules\Foundation\Support\Filters;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Database\Query\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Index-friendly conditions on DATE columns.
 *
 * `whereDate()` compiles to `DATE(column)` on MySQL, which stops the
 * database from using an index on the column. These helpers compare the
 * column itself, and turn "on or before a day" into "before the next day",
 * so they also stay right where a date is stored with a time
 * (`Y-m-d 00:00:00`, as Eloquent writes it on SQLite):
 *
 * ```php
 * QueryBuilder::for(Invoice::class)->allowedFilters(DateFilter::from(), DateFilter::to());
 * DateFilter::between($query, 'journal_entries.date', $from, $to);
 * ```
 */
final class DateFilter
{
    /**
     * The `filter[from]` of an index page: on or after the day. An invalid
     * date leaves the list unfiltered.
     */
    public static function from(string $name = 'from', string $column = 'date'): AllowedFilter
    {
        return AllowedFilter::callback($name, fn (Builder $query, string $date) => self::onOrAfter($query, $column, $date))->delimiter('');
    }

    /**
     * The `filter[to]` of an index page: on or before the day. An invalid
     * date leaves the list unfiltered.
     */
    public static function to(string $name = 'to', string $column = 'date'): AllowedFilter
    {
        return AllowedFilter::callback($name, fn (Builder $query, string $date) => self::onOrBefore($query, $column, $date))->delimiter('');
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function onOrAfter(Builder $query, string $column, CarbonInterface|string $date): Builder
    {
        $day = self::day($date);

        return $day === null ? $query : $query->where($column, '>=', $day->toDateString());
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function onOrBefore(Builder $query, string $column, CarbonInterface|string $date): Builder
    {
        $day = self::day($date);

        return $day === null ? $query : $query->where($column, '<', $day->addDay()->toDateString());
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function before(Builder $query, string $column, CarbonInterface|string $date): Builder
    {
        $day = self::day($date);

        return $day === null ? $query : $query->where($column, '<', $day->toDateString());
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function after(Builder $query, string $column, CarbonInterface|string $date): Builder
    {
        $day = self::day($date);

        return $day === null ? $query : $query->where($column, '>=', $day->addDay()->toDateString());
    }

    /**
     * Both days included.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function between(Builder $query, string $column, CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        return self::onOrBefore(self::onOrAfter($query, $column, $from), $column, $to);
    }

    /**
     * The start of the day, or null when the text is not a date.
     */
    private static function day(CarbonInterface|string $date): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($date instanceof CarbonInterface ? $date->toDateString() : $date)->startOfDay();
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
