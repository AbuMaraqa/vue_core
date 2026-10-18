<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Menu from '~icons/solar/hamburger-menu-linear';
import X from '~icons/solar/close-linear';
import AppLogo from '@/components/layout/AppLogo.vue';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import NotificationBell from '@/components/layout/NotificationBell.vue';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import UserMenu from '@/components/layout/UserMenu.vue';
import { useSidebar } from '@/composables/useSidebar';
import { useTranslations } from '@/composables/useTranslations';
import dashboard from '@/actions/App/Modules/Core/Http/Controllers/DashboardController';

const { isMobileOpen, toggleSidebar } = useSidebar();
const { t } = useTranslations();
</script>

<template>
    <header
        class="sticky top-0 z-9999 flex w-full border-gray-200 bg-white xl:border-b dark:border-gray-800 dark:bg-gray-900"
    >
        <div
            class="flex grow items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 sm:gap-4 lg:py-4 xl:border-b-0 xl:px-6 dark:border-gray-800"
        >
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :title="t('Toggle sidebar')"
                    :class="[
                        'flex size-10 items-center justify-center rounded-lg border-gray-200 text-gray-500 lg:size-11 lg:border dark:border-gray-800 dark:text-gray-400',
                        { 'bg-gray-100 dark:bg-gray-800': isMobileOpen },
                    ]"
                    @click="toggleSidebar"
                >
                    <X v-if="isMobileOpen" class="size-6" />
                    <Menu v-else class="size-5" />
                </button>

                <Link :href="dashboard()" class="xl:hidden">
                    <AppLogo />
                </Link>
            </div>

            <div class="flex items-center gap-2 2xsm:gap-3">
                <LanguageSwitcher />
                <ThemeToggler />
                <NotificationBell />
                <UserMenu />
            </div>
        </div>
    </header>
</template>
