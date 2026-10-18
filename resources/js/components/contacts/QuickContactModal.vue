<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import { useTranslations } from '@/composables/useTranslations';
import { quickStore } from '@/routes/admin/contacts';
import type { ContactOption } from '@/types';

/**
 * Create a customer or vendor without leaving a document form: only the
 * name and mobile are asked; the code is numbered automatically.
 */
const props = defineProps<{
    type: 'customers' | 'vendors';
    /**
     * The name to start with, e.g. what was typed in the picker's search.
     */
    name?: string;
}>();

const emit = defineEmits<{
    created: [contact: ContactOption];
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();

const request = useHttp<{ name: string; mobile: string }, ContactOption>({
    name: '',
    mobile: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        request.clearErrors();
        request.name = props.name ?? '';
        request.mobile = '';
    }
});

async function save(): Promise<void> {
    const isCustomer = props.type === 'customers';

    const contact = await request
        .transform((data) => ({
            name: data.name,
            mobile: data.mobile || null,
            kind: isCustomer ? 'individual' : 'company',
            is_customer: isCustomer,
            is_vendor: !isCustomer,
            is_active: true,
        }))
        .post(quickStore.url());

    if (contact) {
        emit('created', contact);
        open.value = false;
    }
}
</script>

<template>
    <BaseModal
        v-model:open="open"
        :title="type === 'customers' ? t('New customer') : t('New vendor')"
        max-width="sm"
    >
        <form class="mt-4 space-y-5" @submit.prevent="save">
            <BaseField
                :label="t('Name')"
                for="quick_contact_name"
                required
                :error="request.errors.name"
            >
                <BaseInput
                    id="quick_contact_name"
                    v-model="request.name"
                    autofocus
                    :invalid="Boolean(request.errors.name)"
                />
            </BaseField>
            <BaseField
                :label="t('Mobile')"
                for="quick_contact_mobile"
                :error="request.errors.mobile"
            >
                <BaseInput
                    id="quick_contact_mobile"
                    v-model="request.mobile"
                    type="tel"
                    dir="ltr"
                    :invalid="Boolean(request.errors.mobile)"
                />
            </BaseField>

            <div class="flex justify-end gap-3">
                <BaseButton variant="outline" @click="open = false">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton type="submit" :loading="request.processing">
                    {{ t('Create') }}
                </BaseButton>
            </div>
        </form>
    </BaseModal>
</template>
