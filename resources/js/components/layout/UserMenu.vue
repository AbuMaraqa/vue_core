<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import LogOut from '~icons/solar/logout-2-linear';
import ShieldCheck from '~icons/solar/shield-check-linear';
import UserRound from '~icons/solar/user-rounded-linear';
import { onClickOutside } from '@vueuse/core';
import { ref, useTemplateRef } from 'vue';
import BaseAvatar from '@/components/base/BaseAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const page = usePage();
const { t } = useTranslations();

const isOpen = ref(false);
const dropdown = useTemplateRef('dropdown');

onClickOutside(dropdown, () => {
    isOpen.value = false;
});

function handleLogout(): void {
    router.flushAll();
}
</script>

<template>
    <div v-if="page.props.auth.user" ref="dropdown" class="relative">
        <button
            type="button"
            class="flex items-center gap-3 text-gray-700 dark:text-gray-400"
            @click="isOpen = !isOpen"
        >
            <BaseAvatar :name="page.props.auth.user.name" size="lg" />
            <span class="hidden text-theme-sm font-medium sm:block">
                {{ page.props.auth.user.name }}
            </span>
            <ChevronDown
                :class="[
                    'size-5 text-gray-500 transition-transform duration-200 dark:text-gray-400',
                    { 'rotate-180': isOpen },
                ]"
            />
        </button>

        <div
            v-if="isOpen"
            class="animate-fade-in absolute end-0 z-50 mt-4 flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
        >
            <div>
                <span
                    class="block text-theme-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    {{ page.props.auth.user.name }}
                </span>
                <span
                    class="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ page.props.auth.user.email }}
                </span>
            </div>

            <ul
                class="flex flex-col gap-1 border-b border-gray-200 pt-4 pb-3 dark:border-gray-800"
            >
                <li>
                    <Link
                        :href="editProfile()"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                        @click="isOpen = false"
                    >
                        <UserRound
                            class="size-5 text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-300"
                        />
                        {{ t('Edit profile') }}
                    </Link>
                </li>
                <li>
                    <Link
                        :href="editSecurity()"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                        @click="isOpen = false"
                    >
                        <ShieldCheck
                            class="size-5 text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-300"
                        />
                        {{ t('Security') }}
                    </Link>
                </li>
            </ul>

            <Link
                :href="logout()"
                as="button"
                class="group mt-3 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                data-test="logout-button"
                @click="handleLogout"
            >
                <LogOut
                    class="size-5 text-gray-500 group-hover:text-gray-700 rtl:rotate-180 dark:text-gray-400 dark:group-hover:text-gray-300"
                />
                {{ t('Sign out') }}
            </Link>
        </div>
    </div>
</template>
