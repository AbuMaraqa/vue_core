<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import EmployeeForm from '@/components/payroll/EmployeeForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/payroll/employees';
import type { ComboboxOption } from '@/types';
import type { PayComponentOption } from '@/types/payroll';

defineProps<{
    costCenters: ComboboxOption[];
    payComponents: PayComponentOption[];
    defaults: { hire_date: string };
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Add Employee')"
            :breadcrumbs="[
                { title: t('Employees'), href: index() },
                { title: t('Add Employee') },
            ]"
        />

        <EmployeeForm
            :cost-centers="costCenters"
            :pay-components="payComponents"
            :defaults="defaults"
        />
    </div>
</template>
