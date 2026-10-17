<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import History from '~icons/solar/history-linear';
import Images from '~icons/solar/gallery-linear';
import Circle from '~icons/solar/record-linear';
import Settings from '~icons/solar/settings-linear';
import Users from '~icons/solar/users-group-rounded-linear';
import LayoutDashboard from '~icons/solar/widget-linear';

/**
 * Icons referenced by name from the server, e.g. `SidebarItem::make('Users')->icon('users')`.
 *
 * They come from the Solar icon set (https://icon-sets.iconify.design/solar/).
 * When a module needs a new icon, import it above and add its name here.
 * Unknown names fall back to a small circle.
 */
const icons: Record<string, Component> = {
    history: History,
    images: Images,
    'layout-dashboard': LayoutDashboard,
    settings: Settings,
    users: Users,
};

const props = defineProps<{
    name: string | null;
}>();

const icon = computed(() => (props.name && icons[props.name]) || Circle);
</script>

<template>
    <component :is="icon" />
</template>
