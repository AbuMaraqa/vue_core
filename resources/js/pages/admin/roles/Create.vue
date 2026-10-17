<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import RoleForm from '@/components/roles/RoleForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/roles';
import type { PermissionGroup } from '@/types';

defineProps<{
    permissionGroups: PermissionGroup[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Create role')"
            :breadcrumbs="[
                { title: t('Roles & Permissions'), href: index() },
                { title: t('Create role') },
            ]"
        />

        <RoleForm :permission-groups="permissionGroups" />
    </div>
</template>
