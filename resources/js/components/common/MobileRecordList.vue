<script setup lang="ts">
/**
 * The phone version of a list: a stack of `MobileRecordCard`s shown below the
 * `sm` breakpoint, where the page's table is hidden.
 */
</script>

<template>
    <ul
        class="divide-y divide-gray-100 border-t border-gray-100 sm:hidden dark:divide-gray-800 dark:border-gray-800"
    >
        <slot />
    </ul>
</template>
