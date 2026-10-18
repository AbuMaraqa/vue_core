<?php

namespace App\Modules\Core\Support\Dashboard;

use Illuminate\Support\Str;

/**
 * A key figure of the dashboard, e.g. "Money in cash and banks".
 */
class DashboardStat
{
    protected string $value = '0';

    protected ?string $currency = null;

    protected int $decimals = 0;

    protected ?string $hint = null;

    protected ?string $icon = null;

    protected string $tone = 'brand';

    protected ?string $url = null;

    protected int $sort = 100;

    final public function __construct(protected string $label) {}

    /**
     * Create a figure with the given translatable label.
     */
    public static function make(string $label): static
    {
        return new static($label);
    }

    /**
     * A plain number, formatted on the frontend.
     */
    public function value(int|string $value): static
    {
        $this->value = (string) $value;

        return $this;
    }

    /**
     * An amount of money in the given currency.
     */
    public function money(string $amount, string $currency, int $decimals = 2): static
    {
        $this->value = $amount;
        $this->currency = $currency;
        $this->decimals = $decimals;

        return $this;
    }

    /**
     * A short translatable explanation shown under the figure.
     */
    public function hint(?string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * The color of the icon: brand, success, error, warning or info.
     */
    public function tone(string $tone): static
    {
        $this->tone = $tone;

        return $this;
    }

    /**
     * The page that explains the figure.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = []): static
    {
        $this->url = route($name, $parameters, absolute: false);

        return $this;
    }

    public function sort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * @return array{key: string, label: string, value: string, currency: string|null, decimals: int, hint: string|null, icon: string|null, tone: string, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => Str::slug($this->label),
            'label' => $this->label,
            'value' => $this->value,
            'currency' => $this->currency,
            'decimals' => $this->decimals,
            'hint' => $this->hint,
            'icon' => $this->icon,
            'tone' => $this->tone,
            'url' => $this->url,
        ];
    }
}
