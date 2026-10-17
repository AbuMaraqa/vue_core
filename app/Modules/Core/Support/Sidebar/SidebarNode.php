<?php

namespace App\Modules\Core\Support\Sidebar;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use UnitEnum;

use function Illuminate\Support\enum_value;

/**
 * Shared behaviour of everything that can be rendered inside the sidebar.
 */
abstract class SidebarNode
{
    protected ?string $icon = null;

    protected ?string $badge = null;

    protected int $sort = 100;

    /**
     * @var list<string>
     */
    protected array $permissions = [];

    final public function __construct(protected string $title) {}

    /**
     * Create a new node with the given translatable title.
     */
    public static function make(string $title): static
    {
        return new static($title);
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
     * Show a small badge next to the title.
     */
    public function badge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * Set the position of the node. Lower values are rendered first.
     */
    public function sort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Require every given permission before the node is visible.
     */
    public function can(UnitEnum|string ...$permissions): static
    {
        $this->permissions = array_values(array_map(fn (UnitEnum|string $permission): string => enum_value($permission), $permissions));

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * Get a stable identifier used by the frontend to track open menus.
     */
    public function key(): string
    {
        return Str::slug($this->title);
    }

    /**
     * Determine whether the user holds every permission the node requires.
     */
    public function isAuthorized(?Authorizable $user): bool
    {
        if ($this->permissions === []) {
            return true;
        }

        return $user !== null && collect($this->permissions)->every(fn (string $permission): bool => $user->can($permission));
    }

    /**
     * Resolve the node for the current request, or null when it should be hidden.
     *
     * @return array<string, mixed>|null
     */
    abstract public function resolve(Request $request): ?array;
}
