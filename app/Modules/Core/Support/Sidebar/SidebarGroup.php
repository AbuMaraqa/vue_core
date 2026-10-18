<?php

namespace App\Modules\Core\Support\Sidebar;

use Illuminate\Http\Request;

/**
 * A collapsible menu containing several sidebar items.
 *
 * A group is hidden when the user is not authorized to see it, or when none of
 * its children are visible to the user.
 */
class SidebarGroup extends SidebarNode
{
    /**
     * @var list<SidebarItem>
     */
    protected array $children = [];

    public function add(SidebarItem ...$items): static
    {
        array_push($this->children, ...$items);

        return $this;
    }

    /**
     * @return array{type: 'group', key: string, title: string, icon: string|null, badge: string|null, active: bool, children: list<array<string, mixed>>}|null
     */
    public function resolve(Request $request): ?array
    {
        if (! $this->isVisible($request->user())) {
            return null;
        }

        $children = collect($this->children)
            ->sortBy(fn (SidebarItem $item): int => $item->getSort())
            ->map(fn (SidebarItem $item): ?array => $item->resolve($request))
            ->filter()
            ->values();

        if ($children->isEmpty()) {
            return null;
        }

        return [
            'type' => 'group',
            'key' => $this->key(),
            'title' => $this->title(),
            'icon' => $this->icon,
            'badge' => $this->badge,
            'active' => $children->contains('active', true),
            'children' => array_values($children->all()),
        ];
    }
}
