<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import EmployeeForm from '@/components/payroll/EmployeeForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/payroll/employees';
import type { ComboboxOption } from '@/types';
import type { EmployeeForEdit, PayComponentOption } from '@/types/payroll';

defineProps<{
    employee: EmployeeForEdit;
    costCenters: ComboboxOption[];
    payComponents: PayComponentOption[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Edit Employee')"
            :breadcrumbs="[
                { title: t('Employees'), href: index() },
                { title: employee.name, href: show({ employee: employee.id }) },
                { title: t('Edit') },
            ]"
        />

        <EmployeeForm
            :employee="employee"
            :cost-centers="costCenters"
            :pay-components="payComponents"
        />
    </div>
</template>
