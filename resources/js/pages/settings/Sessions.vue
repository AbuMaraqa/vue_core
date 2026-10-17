<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import Logout from '~icons/solar/logout-3-linear';
import Monitor from '~icons/solar/monitor-linear';
import Smartphone from '~icons/solar/smartphone-linear';
import { ref } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { describeDevice } from '@/lib/device';
import { formatDate, formatRelative } from '@/lib/format';
import { destroy, destroyOthers } from '@/routes/sessions';
import type { AuthenticationLog, BrowserSession, Resource } from '@/types';

const props = defineProps<{
    /**
     * False when sessions are not stored in the database and cannot be listed.
     */
    available: boolean;
    sessions: BrowserSession[];
    recentLogins: Resource<AuthenticationLog[]>;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const hasOtherSessions = props.sessions.some((session) => !session.is_current);

const revocation = useConfirmDelete((session: BrowserSession) =>
    destroy({ sessionKey: session.key }),
);

const confirmingOthers = ref(false);
const signingOutOthers = ref(false);

function signOutOthers(): void {
    router.visit(destroyOthers(), {
        preserveScroll: true,
        onStart: () => (signingOutOthers.value = true),
        onFinish: () => {
            signingOutOthers.value = false;
            confirmingOthers.value = false;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Active sessions')"
            :breadcrumbs="[{ title: t('Active sessions') }]"
        />

        <div class="space-y-6">
            <BaseCard
                :title="t('Browsers and devices')"
                :description="
                    t(
                        'Where you are signed in. Sign out of any session you do not recognise.',
                    )
                "
            >
                <BaseAlert
                    v-if="!available"
                    variant="info"
                    :title="
                        t(
                            'Sessions can only be listed when they are stored in the database.',
                        )
                    "
                />

                <ul
                    v-else
                    class="divide-y divide-gray-100 dark:divide-gray-800"
                >
                    <li
                        v-for="session in sessions"
                        :key="session.key"
                        class="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                        >
                            <Smartphone
                                v-if="session.device.is_mobile"
                                class="size-5"
                            />
                            <Monitor v-else class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex flex-wrap items-center gap-2 font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ describeDevice(session.device, t) }}
                                <BaseBadge
                                    v-if="session.is_current"
                                    color="success"
                                >
                                    {{ t('This device') }}
                                </BaseBadge>
                            </p>
                            <p
                                class="text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                <span dir="ltr">{{
                                    session.ip_address ?? '—'
                                }}</span>
                                ·
                                {{
                                    session.is_current
                                        ? t('Active now')
                                        : formatRelative(
                                              session.last_active_at,
                                              locale,
                                          )
                                }}
                            </p>
                        </div>
                        <BaseIconButton
                            v-if="!session.is_current"
                            color="danger"
                            :label="t('Sign out this session')"
                            @click="revocation.ask(session)"
                        >
                            <Logout />
                        </BaseIconButton>
                    </li>
                </ul>

                <div
                    v-if="available && hasOtherSessions"
                    class="mt-6 flex justify-end border-t border-gray-100 pt-5 dark:border-gray-800"
                >
                    <BaseButton
                        variant="outline"
                        size="sm"
                        @click="confirmingOthers = true"
                    >
                        <Logout class="size-4" />
                        {{ t('Sign out of all other sessions') }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard
                :title="t('Recent sign-ins')"
                :description="
                    t('The latest sign-ins and attempts on your account.')
                "
            >
                <ul
                    v-if="recentLogins.data.length"
                    class="divide-y divide-gray-100 dark:divide-gray-800"
                >
                    <li
                        v-for="login in recentLogins.data"
                        :key="login.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="flex items-center gap-3">
                            <BaseBadge :color="login.event_color">
                                {{ login.event_label }}
                            </BaseBadge>
                            <span
                                class="text-theme-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ describeDevice(login.device, t) }}
                            </span>
                        </div>
                        <span
                            class="text-theme-sm text-gray-500 dark:text-gray-400"
                            :title="
                                formatDate(login.created_at, locale, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                })
                            "
                        >
                            <span dir="ltr">{{ login.ip_address ?? '—' }}</span>
                            ·
                            {{ formatRelative(login.created_at, locale) }}
                        </span>
                    </li>
                </ul>
                <EmptyState v-else :message="t('No sign-ins recorded yet.')" />
            </BaseCard>
        </div>

        <ConfirmDialog
            :open="revocation.target !== null"
            :title="t('Sign out this session')"
            :message="
                t('The device will need to sign in again to use your account.')
            "
            :confirm-label="t('Sign out')"
            :processing="revocation.processing"
            @update:open="(open) => !open && revocation.cancel()"
            @confirm="revocation.confirm"
        />

        <ConfirmDialog
            v-model:open="confirmingOthers"
            :title="t('Sign out of all other sessions')"
            :message="
                t(
                    'Every other browser and device will need to sign in again to use your account.',
                )
            "
            :confirm-label="t('Sign out')"
            :processing="signingOutOthers"
            @confirm="signOutOthers"
        />
    </div>
</template>
