<?php

namespace App\Modules\Core\Support\Sidebar;

use Illuminate\Http\Request;

/**
 * The admin sidebar registry.
 *
 * Modules describe their navigation once while booting. The tree is resolved
 * per request, so permissions and the active state always match the current
 * user and route.
 */
class Sidebar
{
    /**
     * @var array<string, SidebarSection>
     */
    protected array $sections = [];

    /**
     * Get an existing section or create it, so several modules can share one section.
     */
    public function section(string $key, ?string $title = null, int $sort = 100): SidebarSection
    {
        return $this->sections[$key] ??= new SidebarSection($key, $title ?? $key, $sort);
    }

    /**
     * Resolve the visible sidebar tree for the current request.
     *
     * @return list<array<string, mixed>>
     */
    public function resolve(Request $request): array
    {
        $sections = collect($this->sections)
            ->sortBy(fn (SidebarSection $section): int => $section->getSort())
            ->map(fn (SidebarSection $section): ?array => $section->resolve($request))
            ->filter();

        return array_values($sections->all());
    }
}
