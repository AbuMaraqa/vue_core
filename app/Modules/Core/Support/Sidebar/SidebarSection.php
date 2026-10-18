<?php

namespace App\Modules\Core\Support\Sidebar;

use App\Modules\Core\Settings\AppearanceSettings;
use Illuminate\Http\Request;

/**
 * A titled block of the sidebar, e.g. "Menu" or "System", that any module can add nodes to.
 */
class SidebarSection
{
    /**
     * @var list<SidebarNode>
     */
    protected array $nodes = [];

    protected ?string $simpleTitle = null;

    public function __construct(
        public readonly string $key,
        protected string $title,
        protected int $sort = 100,
    ) {}

    public function add(SidebarNode ...$nodes): static
    {
        array_push($this->nodes, ...$nodes);

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

    /**
     * Get the title shown to the user in the current interface mode.
     */
    public function title(): string
    {
        return $this->simpleTitle !== null && app(AppearanceSettings::class)->simple_mode
            ? $this->simpleTitle
            : $this->title;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * @return array{type: 'section', key: string, title: string, items: list<array<string, mixed>>}|null
     */
    public function resolve(Request $request): ?array
    {
        $items = collect($this->nodes)
            ->sortBy(fn (SidebarNode $node): int => $node->getSort())
            ->map(fn (SidebarNode $node): ?array => $node->resolve($request))
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'type' => 'section',
            'key' => $this->key,
            'title' => $this->title(),
            'items' => array_values($items->all()),
        ];
    }
}
