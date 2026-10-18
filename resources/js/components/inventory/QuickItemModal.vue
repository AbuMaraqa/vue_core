<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { quickStore } from '@/routes/admin/inventory/items';
import type { ComboboxOption, ItemOption } from '@/types';

/**
 * Create an item without leaving a document form: its name, whether it is
 * kept in stock, its price and unit. The code is numbered automatically and
 * the accounts come from the defaults.
 */
const props = withDefaults(
    defineProps<{
        units?: ComboboxOption[];
        /**
         * Whether the price asked is the sale price (sales documents) or the
         * purchase price.
         */
        isSales: boolean;
        /**
         * The name to start with, e.g. what was typed in the picker's search.
         */
        name?: string;
    }>(),
    { units: () => [], name: '' },
);

const emit = defineEmits<{
    created: [item: ItemOption];
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();
const { locale } = useLocale();

const request = useHttp<
    {
        name: string;
        type: 'stock' | 'service';
        price: string;
        unit_id: number | string | null;
    },
    ItemOption
>({
    name: '',
    type: 'stock',
    price: '',
    unit_id: null,
});

/**
 * The server validates the name per language, e.g. `name.ar`.
 */
const errors = computed(
    () => request.errors as Record<string, string | undefined>,
);

const nameError = computed(
    () => errors.value.name ?? errors.value[`name.${locale.value}`],
);

const priceError = computed(
    () => errors.value[props.isSales ? 'sale_price' : 'purchase_price'],
);

watch(open, (isOpen) => {
    if (isOpen) {
        request.clearErrors();
        request.name = props.name;
        request.type = 'stock';
        request.price = '';
        request.unit_id = null;
    }
});

async function save(): Promise<void> {
    const item = await request
        .transform((data) => ({
            name: { [locale.value]: data.name },
            type: data.type,
            unit_id: data.unit_id || null,
            [props.isSales ? 'sale_price' : 'purchase_price']:
                data.price || null,
            is_active: true,
        }))
        .post(quickStore.url());

    if (item) {
        emit('created', item);
        open.value = false;
    }
}
</script>

<template>
    <BaseModal v-model:open="open" :title="t('New item')" max-width="sm">
        <form class="mt-4 space-y-5" @submit.prevent="save">
            <BaseField
                :label="t('Name')"
                for="quick_item_name"
                required
                :error="nameError"
            >
                <BaseInput
                    id="quick_item_name"
                    v-model="request.name"
                    autofocus
                    :invalid="Boolean(nameError)"
                />
            </BaseField>
            <BaseField
                :label="t('Type')"
                for="quick_item_type"
                required
                :error="request.errors.type"
            >
                <BaseSelect
                    id="quick_item_type"
                    v-model="request.type"
                    :invalid="Boolean(request.errors.type)"
                >
                    <option value="stock">{{ t('Stock item') }}</option>
                    <option value="service">{{ t('Service') }}</option>
                </BaseSelect>
            </BaseField>
            <BaseField
                :label="isSales ? t('Sale price') : t('Purchase price')"
                for="quick_item_price"
                :error="priceError"
            >
                <BaseInput
                    id="quick_item_price"
                    v-model="request.price"
                    type="number"
                    step="any"
                    min="0"
                    dir="ltr"
                    class="font-mono"
                    :invalid="Boolean(priceError)"
                />
            </BaseField>
            <BaseField
                v-if="units.length"
                :label="t('Unit')"
                :error="errors.unit_id"
            >
                <BaseCombobox
                    v-model="request.unit_id"
                    :options="units"
                    clearable
                    :placeholder="t('No unit')"
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
