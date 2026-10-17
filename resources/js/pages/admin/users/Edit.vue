<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import UserForm from '@/components/users/UserForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/users';
import type { ManagedUser, Resource } from '@/types';

defineProps<{
    user: Resource<ManagedUser>;
    roles: string[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Edit User')"
            :breadcrumbs="[
                { title: t('Users'), href: index() },
                { title: user.data.name },
            ]"
        />

        <UserForm :user="user.data" :roles="roles" />
    </div>
</template>
