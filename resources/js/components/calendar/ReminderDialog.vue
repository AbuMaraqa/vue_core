<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import Check from '~icons/solar/check-read-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { ref, watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import { useTranslations } from '@/composables/useTranslations';
import { calendarColors } from '@/lib/calendar';
import { destroy, store, update } from '@/routes/admin/calendar/reminders';
import type { CalendarColor, CalendarEntry } from '@/types';

/**
 * Add a reminder on a day, or change or delete one of your own.
 */
const props = defineProps<{
    /**
     * The reminder to edit, or null to add one on `date`.
     */
    reminder: CalendarEntry | null;
    date: string;
    colors: CalendarColor[];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useTranslations();

const form = useForm({
    title: '',
    date: '',
    time: '',
    notes: '',
    color: 'brand' as CalendarColor,
    is_shared: false,
});

const deleting = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        title: props.reminder?.title ?? '',
        date: props.reminder?.date ?? props.date,
        time: props.reminder?.time ?? '',
        notes: props.reminder?.data.notes ?? '',
        color: props.reminder?.color ?? 'brand',
        is_shared: props.reminder?.data.is_shared ?? false,
    });
    form.reset();
});

function submit(): void {
    const reminderId = props.reminder?.data.reminder_id;

    form.submit(reminderId ? update({ reminder: reminderId }) : store(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

function remove(): void {
    const reminderId = props.reminder?.data.reminder_id;

    if (!reminderId) {
        return;
    }

    router.visit(destroy({ reminder: reminderId }), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <BaseModal
        v-model:open="open"
        :title="reminder ? t('Edit reminder') : t('New reminder')"
        max-width="md"
    >
        <form id="reminder-form" class="space-y-4" @submit.prevent="submit">
            <BaseField
                :label="t('Title')"
                for="reminder-title"
                required
                :error="form.errors.title"
            >
                <BaseInput
                    id="reminder-title"
                    v-model="form.title"
                    :placeholder="t('e.g. Deliver the kitchen to the customer')"
                    :invalid="Boolean(form.errors.title)"
                    autofocus
                />
            </BaseField>
            <div class="grid grid-cols-2 gap-4">
                <BaseField
                    :label="t('Date')"
                    for="reminder-date"
                    required
                    :error="form.errors.date"
                >
                    <BaseInput
                        id="reminder-date"
                        v-model="form.date"
                        type="date"
                        :invalid="Boolean(form.errors.date)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Time')"
                    for="reminder-time"
                    :error="form.errors.time"
                >
                    <BaseInput
                        id="reminder-time"
                        v-model="form.time"
                        type="time"
                        :invalid="Boolean(form.errors.time)"
                    />
                </BaseField>
            </div>
            <BaseField
                :label="t('Notes')"
                for="reminder-notes"
                :error="form.errors.notes"
            >
                <BaseTextarea
                    id="reminder-notes"
                    v-model="form.notes"
                    rows="3"
                />
            </BaseField>
            <BaseField :label="t('Color')" :error="form.errors.color">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="color in colors"
                        :key="color"
                        type="button"
                        class="flex size-8 items-center justify-center rounded-full text-white ring-offset-2 transition dark:ring-offset-gray-900"
                        :class="[
                            calendarColors[color].dot,
                            form.color === color ? 'ring-2 ring-gray-400' : '',
                        ]"
                        :aria-label="color"
                        :aria-pressed="form.color === color"
                        @click="form.color = color"
                    >
                        <Check v-if="form.color === color" class="size-4" />
                    </button>
                </div>
            </BaseField>
            <BaseCheckbox v-model="form.is_shared">
                {{ t('Share with everyone who can see the calendar') }}
            </BaseCheckbox>
        </form>

        <template #footer>
            <BaseButton
                v-if="reminder"
                variant="ghost"
                class="me-auto text-error-600 dark:text-error-500"
                :loading="deleting"
                @click="remove"
            >
                <Trash2 class="size-4" />
                {{ t('Delete') }}
            </BaseButton>
            <BaseButton variant="outline" @click="open = false">
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton
                type="submit"
                form="reminder-form"
                :loading="form.processing"
            >
                {{ reminder ? t('Save changes') : t('Add reminder') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>
