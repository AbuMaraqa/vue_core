<?php

namespace App\Modules\Foundation\Concerns;

/**
 * Helpers for backed enums shown in the interface, e.g. in selects and badges.
 *
 * The enum defines `label()`, returning a translated string:
 *
 * ```php
 * enum OrderStatus: string
 * {
 *     use EnumOptions;
 *
 *     case Pending = 'pending';
 *
 *     public function label(): string
 *     {
 *         return __(str($this->name)->headline()->value());
 *     }
 * }
 * ```
 */
trait EnumOptions
{
    abstract public function label(): string;

    /**
     * The cases as `{value, label}` pairs for a select.
     *
     * @return list<array{value: string|int, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    /**
     * @return list<string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
