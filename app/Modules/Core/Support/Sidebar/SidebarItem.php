<?php

namespace App\Modules\Core\Support\Sidebar;

use Illuminate\Http\Request;

/**
 * A single link in the sidebar pointing to a named route.
 */
class SidebarItem extends SidebarNode
{
    protected ?string $route = null;

    /**
     * @var array<string, mixed>
     */
    protected array $routeParameters = [];

    /**
     * @var list<string>
     */
    protected array $activePatterns = [];

    /**
     * Link the item to a named route.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = []): static
    {
        $this->route = $name;
        $this->routeParameters = $parameters;

        return $this;
    }

    /**
     * Mark the item as active for additional route name patterns, e.g. `admin.users.*`.
     */
    public function activeWhen(string ...$patterns): static
    {
        $this->activePatterns = array_values($patterns);

        return $this;
    }

    public function isActive(Request $request): bool
    {
        $patterns = array_filter([$this->route, ...$this->activePatterns]);

        return $patterns !== [] && $request->routeIs(...$patterns);
    }

    /**
     * @return array{type: 'item', key: string, title: string, icon: string|null, badge: string|null, url: string|null, active: bool}|null
     */
    public function resolve(Request $request): ?array
    {
        if (! $this->isVisible($request->user())) {
            return null;
        }

        return [
            'type' => 'item',
            'key' => $this->key(),
            'title' => $this->title(),
            'icon' => $this->icon,
            'badge' => $this->badge,
            'url' => $this->route ? route($this->route, $this->routeParameters, absolute: false) : null,
            'active' => $this->isActive($request),
        ];
    }
}
