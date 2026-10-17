<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import {
    Boxes,
    ChartColumn,
    Circle,
    History,
    LayoutDashboard,
    Package,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Tags,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';

/**
 * Icons referenced by name from the server, e.g. by sidebar items.
 * Register a new icon here when a module needs it.
 */
const icons: Record<string, LucideIcon> = {
    'chart-column': ChartColumn,
    boxes: Boxes,
    history: History,
    'layout-dashboard': LayoutDashboard,
    package: Package,
    settings: Settings,
    'shield-check': ShieldCheck,
    'shopping-cart': ShoppingCart,
    tags: Tags,
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
