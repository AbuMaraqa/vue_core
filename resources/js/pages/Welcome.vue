<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    LayoutDashboard,
    Languages,
    ShieldCheck,
} from '@lucide/vue';
import AppLogo from '@/components/layout/AppLogo.vue';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { login, logout, register } from '@/routes';
import { dashboard } from '@/routes/admin';

const page = usePage();
const { t } = useTranslations();
const { can } = usePermissions();

const features = [
    {
        icon: LayoutDashboard,
        title: 'Modular dashboard',
        description:
            'Every module registers its own pages, sidebar entries and permissions.',
    },
    {
        icon: ShieldCheck,
        title: 'Roles & permissions',
        description:
            'Fine-grained access control for every action in the admin panel.',
    },
    {
        icon: Languages,
        title: 'Multilingual',
        description:
            'Arabic and English out of the box, with full right-to-left support.',
    },
];
</script>

<template>
    <Head :title="t('Welcome')" />

    <div class="min-h-screen bg-white dark:bg-gray-900">
        <header
            class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-6 py-6"
        >
            <AppLogo />
            <nav class="flex items-center gap-3">
                <LanguageSwitcher />
                <ThemeToggler />
                <template v-if="page.props.auth.user">
                    <Link
                        v-if="can('dashboard.access')"
                        :href="dashboard()"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                    >
                        {{ t('Dashboard') }}
                    </Link>
                    <Link
                        v-else
                        :href="logout()"
                        as="button"
                        class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        {{ t('Sign out') }}
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        {{ t('Sign In') }}
                    </Link>
                    <Link
                        :href="register()"
                        class="hidden rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:inline-flex"
                    >
                        {{ t('Sign Up') }}
                    </Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-16 lg:py-24">
            <div class="mx-auto max-w-2xl text-center">
                <h1
                    class="text-title-sm font-bold text-gray-800 sm:text-title-md dark:text-white/90"
                >
                    {{ page.props.name }}
                </h1>
                <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">
                    {{ t('Manage your store from one powerful dashboard.') }}
                </p>
                <Link
                    :href="page.props.auth.user ? dashboard() : login()"
                    class="mt-8 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"
                >
                    {{ t('Get started') }}
                    <ArrowRight class="size-4 rtl:rotate-180" />
                </Link>
            </div>

            <div class="mt-16 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400"
                    >
                        <component :is="feature.icon" class="size-6" />
                    </span>
                    <h3
                        class="mt-5 text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ t(feature.title) }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ t(feature.description) }}
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
