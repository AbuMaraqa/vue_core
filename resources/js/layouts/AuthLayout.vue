<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import { Toaster } from '@/components/ui/sonner';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const { t } = useTranslations();
</script>

<template>
    <div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
        <div
            class="relative flex min-h-screen w-full flex-col justify-center lg:flex-row dark:bg-gray-900"
        >
            <div class="flex w-full flex-1 flex-col lg:w-1/2">
                <div
                    class="mx-auto flex w-full max-w-md items-center justify-end gap-3 pt-10"
                >
                    <LanguageSwitcher />
                    <ThemeToggler />
                </div>

                <div
                    class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10"
                >
                    <div class="mb-5 sm:mb-8">
                        <h1
                            class="mb-2 text-title-sm font-semibold text-gray-800 sm:text-title-md dark:text-white/90"
                        >
                            {{ title ? t(title) : '' }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ t(description) }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>

            <div
                class="relative hidden h-auto w-full items-center bg-brand-950 lg:grid lg:w-1/2 dark:bg-white/5"
            >
                <div class="z-1 flex items-center justify-center">
                    <div
                        class="absolute end-0 top-0 -z-1 w-full max-w-[250px] xl:max-w-[450px]"
                    >
                        <img src="/images/shape/grid-01.svg" alt="" />
                    </div>
                    <div
                        class="absolute start-0 bottom-0 -z-1 w-full max-w-[250px] rotate-180 xl:max-w-[450px]"
                    >
                        <img src="/images/shape/grid-01.svg" alt="" />
                    </div>

                    <div class="flex max-w-xs flex-col items-center">
                        <Link
                            :href="home()"
                            class="mb-4 flex items-center gap-3 text-white"
                        >
                            <img
                                v-if="page.props.theme.logoUrl"
                                :src="page.props.theme.logoUrl"
                                :alt="page.props.name"
                                class="h-16 max-w-[240px] rounded-xl bg-white/95 object-contain p-2"
                            />
                            <span
                                v-else
                                class="flex size-12 items-center justify-center rounded-xl bg-brand-500"
                            >
                                <AppLogoIcon class="size-7 fill-current" />
                            </span>
                            <span class="text-2xl font-semibold">
                                {{ page.props.name }}
                            </span>
                        </Link>
                        <p class="text-center text-gray-400 dark:text-white/60">
                            {{
                                t(
                                    'Manage your store from one powerful dashboard.',
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <Toaster rich-colors />
    </div>
</template>
