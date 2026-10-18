<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import { computed, ref, watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { index, store } from '@/routes/admin/assets/assets';
import type { ComboboxOption, SelectOption } from '@/types';
import type { AssetCategoryOption } from '@/types/assets';

const props = defineProps<{
    categories: AssetCategoryOption[];
    methods: SelectOption[];
    costCenters: ComboboxOption[];
    accounts: ComboboxOption[];
    defaults: { acquisition_date: string };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const form = useForm({
    name: '',
    category_id: null as number | string | null,
    serial_number: '',
    location: '',
    cost_center_id: null as number | string | null,
    acquisition_date: props.defaults.acquisition_date,
    depreciation_start_date: props.defaults.acquisition_date,
    cost: '',
    salvage_value: '',
    method: 'straight_line' as string,
    useful_life_months: '' as number | string,
    declining_rate: '',
    opening_depreciation: '',
    funding_account_id: null as number | string | null,
    notes: '',
});

/**
 * The depreciation start follows the acquisition date until it is changed.
 */
watch(
    () => form.acquisition_date,
    (date, previous) => {
        if (form.depreciation_start_date === previous) {
            form.depreciation_start_date = date;
        }
    },
);

function chooseCategory(option: ComboboxOption | null): void {
    const category = props.categories.find(
        (candidate) => candidate.value === option?.value,
    );

    if (category) {
        form.method = category.method;
        form.useful_life_months = category.useful_life_months;
    }
}

const isDeclining = computed(() => form.method === 'declining_balance');

const lifeInYears = computed((): string | null => {
    const months = Number(form.useful_life_months);

    return months > 0
        ? t(':years years', {
              years: formatDecimal(
                  months / 12,
                  locale.value,
                  months % 12 === 0 ? 0 : 2,
              ),
          })
        : null;
});

const isCarriedOverOpen = ref(false);

watch(
    () => form.errors.opening_depreciation,
    (error) => {
        if (error) {
            isCarriedOverOpen.value = true;
        }
    },
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        serial_number: data.serial_number || null,
        location: data.location || null,
        depreciation_start_date: data.depreciation_start_date || null,
        salvage_value: data.salvage_value || null,
        useful_life_months: data.useful_life_months || null,
        declining_rate: isDeclining.value ? data.declining_rate || null : null,
        opening_depreciation: data.opening_depreciation || null,
        notes: data.notes || null,
    })).submit(store(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Register Asset')"
            :breadcrumbs="[
                { title: t('Fixed Assets'), href: index() },
                { title: t('Register asset') },
            ]"
        />

        <form class="grid grid-cols-12 gap-6" @submit.prevent="submit">
            <div class="col-span-12 space-y-6 lg:col-span-8">
                <BaseCard :title="t('Asset details')">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <BaseField
                            class="sm:col-span-2"
                            :label="t('Name')"
                            for="name"
                            required
                            :error="form.errors.name"
                        >
                            <BaseInput
                                id="name"
                                v-model="form.name"
                                :invalid="Boolean(form.errors.name)"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Category')"
                            required
                            :error="form.errors.category_id"
                        >
                            <BaseCombobox
                                v-model="form.category_id"
                                :options="categories"
                                :placeholder="t('Choose a category')"
                                :invalid="Boolean(form.errors.category_id)"
                                @select="chooseCategory"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Cost center')"
                            :error="form.errors.cost_center_id"
                        >
                            <BaseCombobox
                                v-model="form.cost_center_id"
                                :options="costCenters"
                                clearable
                                :placeholder="t('None')"
                                :invalid="Boolean(form.errors.cost_center_id)"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Serial number')"
                            for="serial_number"
                            :error="form.errors.serial_number"
                        >
                            <BaseInput
                                id="serial_number"
                                v-model="form.serial_number"
                                dir="ltr"
                                class="font-mono"
                                :invalid="Boolean(form.errors.serial_number)"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Location')"
                            for="location"
                            :error="form.errors.location"
                        >
                            <BaseInput
                                id="location"
                                v-model="form.location"
                                :invalid="Boolean(form.errors.location)"
                            />
                        </BaseField>
                        <BaseField
                            class="sm:col-span-2"
                            :label="t('Notes')"
                            for="notes"
                            :error="form.errors.notes"
                        >
                            <BaseTextarea
                                id="notes"
                                v-model="form.notes"
                                :invalid="Boolean(form.errors.notes)"
                            />
                        </BaseField>
                    </div>
                </BaseCard>

                <BaseCard :title="t('Cost and depreciation')">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <BaseField
                            :label="t('Acquisition date')"
                            for="acquisition_date"
                            required
                            :error="form.errors.acquisition_date"
                        >
                            <BaseInput
                                id="acquisition_date"
                                v-model="form.acquisition_date"
                                type="date"
                                :invalid="Boolean(form.errors.acquisition_date)"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Depreciation start')"
                            for="depreciation_start_date"
                            :hint="
                                t(
                                    'Usually the date the asset is put in use. Depreciation starts in its month.',
                                )
                            "
                            :error="form.errors.depreciation_start_date"
                        >
                            <BaseInput
                                id="depreciation_start_date"
                                v-model="form.depreciation_start_date"
                                type="date"
                                :min="form.acquisition_date"
                                :invalid="
                                    Boolean(form.errors.depreciation_start_date)
                                "
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Cost')"
                            for="cost"
                            required
                            :error="form.errors.cost"
                        >
                            <BaseInput
                                id="cost"
                                v-model="form.cost"
                                type="number"
                                step="any"
                                min="0"
                                dir="ltr"
                                class="font-mono"
                                :invalid="Boolean(form.errors.cost)"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Salvage value')"
                            for="salvage_value"
                            :hint="
                                t(
                                    'What the asset is expected to be worth at the end of its life.',
                                )
                            "
                            :error="form.errors.salvage_value"
                        >
                            <BaseInput
                                id="salvage_value"
                                v-model="form.salvage_value"
                                type="number"
                                step="any"
                                min="0"
                                dir="ltr"
                                class="font-mono"
                                placeholder="0"
                                :invalid="Boolean(form.errors.salvage_value)"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Depreciation method')"
                            for="method"
                            :error="form.errors.method"
                        >
                            <BaseSelect
                                id="method"
                                v-model="form.method"
                                :invalid="Boolean(form.errors.method)"
                            >
                                <option
                                    v-for="method in methods"
                                    :key="method.value"
                                    :value="method.value"
                                >
                                    {{ method.label }}
                                </option>
                            </BaseSelect>
                        </BaseField>
                        <BaseField
                            :label="t('Useful life in months')"
                            for="useful_life_months"
                            :hint="lifeInYears ?? undefined"
                            :error="form.errors.useful_life_months"
                        >
                            <BaseInput
                                id="useful_life_months"
                                v-model="form.useful_life_months"
                                type="number"
                                step="1"
                                min="1"
                                max="1200"
                                dir="ltr"
                                class="font-mono"
                                :invalid="
                                    Boolean(form.errors.useful_life_months)
                                "
                            />
                        </BaseField>
                        <BaseField
                            v-if="isDeclining"
                            :label="t('Yearly rate %')"
                            for="declining_rate"
                            :hint="
                                t(
                                    'Leave empty to use double the straight-line rate.',
                                )
                            "
                            :error="form.errors.declining_rate"
                        >
                            <BaseInput
                                id="declining_rate"
                                v-model="form.declining_rate"
                                type="number"
                                step="any"
                                min="0"
                                max="100"
                                dir="ltr"
                                class="font-mono"
                                :placeholder="t('2 ÷ years (double declining)')"
                                :invalid="Boolean(form.errors.declining_rate)"
                            />
                        </BaseField>
                    </div>
                </BaseCard>
            </div>

            <div class="col-span-12 space-y-6 lg:col-span-4">
                <BaseCard :title="t('Payment')">
                    <BaseField
                        :label="t('Paid from')"
                        :hint="
                            t(
                                'Leave empty if the cost is already in the books, e.g. through a purchase bill.',
                            )
                        "
                        :error="form.errors.funding_account_id"
                    >
                        <BaseCombobox
                            v-model="form.funding_account_id"
                            :options="accounts"
                            clearable
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.funding_account_id)"
                        />
                    </BaseField>
                </BaseCard>

                <div
                    class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-6 py-5 text-start"
                        :aria-expanded="isCarriedOverOpen"
                        @click="isCarriedOverOpen = !isCarriedOverOpen"
                    >
                        <span
                            class="text-base font-medium text-gray-800 dark:text-white/90"
                        >
                            {{ t('Carried over from a previous system') }}
                        </span>
                        <ChevronDown
                            :class="[
                                'size-5 shrink-0 text-gray-500 transition-transform dark:text-gray-400',
                                { 'rotate-180': isCarriedOverOpen },
                            ]"
                        />
                    </button>
                    <div
                        v-show="isCarriedOverOpen"
                        class="border-t border-gray-100 p-4 sm:p-6 dark:border-gray-800"
                    >
                        <BaseField
                            :label="t('Depreciation already taken')"
                            for="opening_depreciation"
                            :hint="
                                t(
                                    'The depreciation taken before this system. The useful life is then the months left.',
                                )
                            "
                            :error="form.errors.opening_depreciation"
                        >
                            <BaseInput
                                id="opening_depreciation"
                                v-model="form.opening_depreciation"
                                type="number"
                                step="any"
                                min="0"
                                dir="ltr"
                                class="font-mono"
                                placeholder="0"
                                :invalid="
                                    Boolean(form.errors.opening_depreciation)
                                "
                            />
                        </BaseField>
                    </div>
                </div>
            </div>

            <div class="col-span-12 flex justify-end gap-3">
                <BaseButton variant="outline" :href="index()">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton type="submit" :loading="form.processing">
                    {{ t('Register asset') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
