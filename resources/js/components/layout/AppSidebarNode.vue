<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import { computed } from 'vue';
import AppIcon from '@/components/common/AppIcon.vue';
import { useSidebar } from '@/composables/useSidebar';
import { useTranslations } from '@/composables/useTranslations';
import type { SidebarGroup, SidebarLink } from '@/types';

const props = defineProps<{
    node: SidebarLink | SidebarGroup;
}>();

const { isWide, openSubmenu, toggleSubmenu } = useSidebar();
const { t } = useTranslations();

const isOpen = computed(
    () => props.node.type === 'group' && openSubmenu.value === props.node.key,
);
</script>

<template>
    <li>
        <template v-if="node.type === 'group'">
            <button
                type="button"
                :class="[
                    'group menu-item',
                    node.active || isOpen
                        ? 'menu-item-active'
                        : 'menu-item-inactive',
                    isWide ? 'xl:justify-start' : 'xl:justify-center',
                ]"
                @click="toggleSubmenu(node.key)"
            >
                <span
                    :class="[
                        '[&>svg]:size-6',
                        node.active || isOpen
                            ? 'menu-item-icon-active'
                            : 'menu-item-icon-inactive',
                    ]"
                >
                    <AppIcon :name="node.icon" />
                </span>
                <template v-if="isWide">
                    <span class="truncate">{{ t(node.title) }}</span>
                    <ChevronDown
                        :class="[
                            'ms-auto size-5 transition-transform duration-200',
                            { 'rotate-180 text-brand-500': isOpen },
                        ]"
                    />
                </template>
            </button>

            <div
                class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                :class="
                    isOpen && isWide ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'
                "
            >
                <ul class="ms-9 space-y-1 overflow-hidden">
                    <li v-for="child in node.children" :key="child.key">
                        <Link
                            v-if="child.url"
                            :href="child.url"
                            :class="[
                                'menu-dropdown-item first:mt-2',
                                child.active
                                    ? 'menu-dropdown-item-active'
                                    : 'menu-dropdown-item-inactive',
                            ]"
                        >
                            {{ t(child.title) }}
                            <span
                                v-if="child.badge"
                                class="ms-auto menu-dropdown-badge"
                            >
                                {{ child.badge }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </template>

        <Link
            v-else-if="node.url"
            :href="node.url"
            :class="[
                'group menu-item',
                node.active ? 'menu-item-active' : 'menu-item-inactive',
                isWide ? 'xl:justify-start' : 'xl:justify-center',
            ]"
        >
            <span
                :class="[
                    '[&>svg]:size-6',
                    node.active
                        ? 'menu-item-icon-active'
                        : 'menu-item-icon-inactive',
                ]"
            >
                <AppIcon :name="node.icon" />
            </span>
            <template v-if="isWide">
                <span class="truncate">{{ t(node.title) }}</span>
                <span v-if="node.badge" class="ms-auto menu-dropdown-badge">
                    {{ node.badge }}
                </span>
            </template>
        </Link>
    </li>
</template>
