<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import RoleForm from '@/components/roles/RoleForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/roles';
import type { PermissionGroup, Resource, Role } from '@/types';

defineProps<{
    role: Resource<Role>;
    permissionGroups: PermissionGroup[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Edit role')"
            :breadcrumbs="[
                { title: t('Roles & Permissions'), href: index() },
                { title: t(role.data.name) },
            ]"
        />

        <RoleForm :role="role.data" :permission-groups="permissionGroups" />
    </div>
</template>
