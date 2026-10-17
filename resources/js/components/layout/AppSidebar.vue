<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Ellipsis from '~icons/solar/menu-dots-linear';
import { watch } from 'vue';
import AppLogo from '@/components/layout/AppLogo.vue';
import AppSidebarNode from '@/components/layout/AppSidebarNode.vue';
import { useSidebar } from '@/composables/useSidebar';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes/admin';

const page = usePage();
const { t } = useTranslations();
const { isExpanded, isMobileOpen, isHovered, isWide, openSubmenu } =
    useSidebar();

// Open the group that contains the current page after every visit.
watch(
    () => page.props.sidebar,
    (sections) => {
        const activeGroup = sections
            .flatMap((section) => section.items)
            .find((item) => item.type === 'group' && item.active);

        if (activeGroup) {
            openSubmenu.value = activeGroup.key;
        }
    },
    { immediate: true },
);
</script>

<template>
    <aside
        :class="[
            'fixed start-0 top-0 z-99999 flex h-screen flex-col border-e border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900',
            isWide ? 'w-[290px]' : 'w-[290px] xl:w-[90px]',
            isMobileOpen
                ? 'translate-x-0'
                : '-translate-x-full xl:translate-x-0 rtl:translate-x-full rtl:xl:translate-x-0',
        ]"
        @mouseenter="isHovered = !isExpanded"
        @mouseleave="isHovered = false"
    >
        <div
            :class="[
                'flex pt-8 pb-7',
                isWide ? 'justify-start' : 'xl:justify-center',
            ]"
        >
            <Link :href="dashboard()">
                <AppLogo :collapsed="!isWide" />
            </Link>
        </div>

        <div class="no-scrollbar flex flex-col overflow-y-auto">
            <nav class="mb-6">
                <div class="flex flex-col gap-4">
                    <div
                        v-for="section in page.props.sidebar"
                        :key="section.key"
                    >
                        <h2
                            :class="[
                                'mb-4 flex text-xs leading-5 text-gray-400 uppercase',
                                isWide ? 'justify-start' : 'xl:justify-center',
                            ]"
                        >
                            <template v-if="isWide">
                                {{ t(section.title) }}
                            </template>
                            <Ellipsis v-else class="size-6" />
                        </h2>
                        <ul class="flex flex-col gap-1">
                            <AppSidebarNode
                                v-for="node in section.items"
                                :key="node.key"
                                :node="node"
                            />
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </aside>
</template>
