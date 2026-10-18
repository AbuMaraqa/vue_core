<?php

namespace App\Modules\Core\Support\Sidebar;

use App\Modules\Core\Settings\AppearanceSettings;
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

    protected bool $advanced = false;

    protected ?string $simpleTitle = null;

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

    /**
     * Hide the node while the interface is in simple mode, e.g. for pages only an accountant needs.
     */
    public function advanced(bool $advanced = true): static
    {
        $this->advanced = $advanced;

        return $this;
    }

    /**
     * Use a plainer title while the interface is in simple mode.
     */
    public function simpleTitle(string $title): static
    {
        $this->simpleTitle = $title;

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * Get the title shown to the user in the current interface mode.
     */
    public function title(): string
    {
        return $this->simpleTitle !== null && $this->isSimpleMode() ? $this->simpleTitle : $this->title;
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
     * Determine whether the node is shown: authorized, and not an advanced node in simple mode.
     */
    public function isVisible(?Authorizable $user): bool
    {
        return ! ($this->advanced && $this->isSimpleMode()) && $this->isAuthorized($user);
    }

    protected function isSimpleMode(): bool
    {
        return app(AppearanceSettings::class)->simple_mode;
    }

    /**
     * Resolve the node for the current request, or null when it should be hidden.
     *
     * @return array<string, mixed>|null
     */
    abstract public function resolve(Request $request): ?array;
}
