import { useLocalStorage, useMediaQuery } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, inject, provide, ref } from 'vue';

export type SidebarContext = {
    isExpanded: ComputedRef<boolean>;
    isMobileOpen: Ref<boolean>;
    isHovered: Ref<boolean>;
    isWide: ComputedRef<boolean>;
    openSubmenu: Ref<string | null>;
    toggleSidebar: () => void;
    closeMobileSidebar: () => void;
    toggleSubmenu: (key: string) => void;
};

const SidebarKey = Symbol('sidebar');

/**
 * Create the sidebar state for the admin layout and share it with its children.
 *
 * The collapsed state is remembered across visits, while the mobile drawer
 * always starts closed.
 */
export function provideSidebar(): SidebarContext {
    const isDesktop = useMediaQuery('(min-width: 1280px)');
    const isPinned = useLocalStorage('sidebar-expanded', true);
    const isMobileOpen = ref(false);
    const isHovered = ref(false);
    const openSubmenu = ref<string | null>(null);

    const isExpanded = computed(() => isDesktop.value && isPinned.value);

    const context: SidebarContext = {
        isExpanded,
        isMobileOpen,
        isHovered,
        isWide: computed(
            () => isExpanded.value || isHovered.value || isMobileOpen.value,
        ),
        openSubmenu,
        toggleSidebar: () => {
            if (isDesktop.value) {
                isPinned.value = !isPinned.value;
            } else {
                isMobileOpen.value = !isMobileOpen.value;
            }
        },
        closeMobileSidebar: () => {
            isMobileOpen.value = false;
        },
        toggleSubmenu: (key: string) => {
            openSubmenu.value = openSubmenu.value === key ? null : key;
        },
    };

    provide(SidebarKey, context);

    return context;
}

export function useSidebar(): SidebarContext {
    const context = inject<SidebarContext>(SidebarKey);

    if (!context) {
        throw new Error('useSidebar() must be used inside the admin layout.');
    }

    return context;
}
