<?php

namespace App\Modules\Core\Support\Dashboard;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Str;

/**
 * A big quick-action button of the dashboard, e.g. "New sale".
 */
class DashboardShortcut
{
    protected ?string $description = null;

    protected ?string $icon = null;

    protected string $tone = 'brand';

    protected string $route = '';

    /**
     * @var array<string, mixed>
     */
    protected array $routeParameters = [];

    protected int $sort = 100;

    /**
     * @var list<string>
     */
    protected array $permissions = [];

    final public function __construct(protected string $title) {}

    /**
     * Create a shortcut with the given translatable title.
     */
    public static function make(string $title): static
    {
        return new static($title);
    }

    /**
     * A short plain explanation shown under the title.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the icon name. Icons are resolved on the frontend by name.
     */
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
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = []): static
    {
        $this->route = $name;
        $this->routeParameters = $parameters;

        return $this;
    }

    public function sort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Require every given permission before the shortcut is shown.
     */
    public function can(string ...$permissions): static
    {
        $this->permissions = array_values($permissions);

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function isAuthorized(?Authorizable $user): bool
    {
        return $user !== null && collect($this->permissions)->every(fn (string $permission): bool => $user->can($permission));
    }

    /**
     * @return array{key: string, title: string, description: string|null, icon: string|null, tone: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'key' => Str::slug($this->title),
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'tone' => $this->tone,
            'url' => route($this->route, $this->routeParameters, absolute: false),
        ];
    }
}
