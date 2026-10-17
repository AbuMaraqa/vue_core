<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import UserForm from '@/components/users/UserForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/users';

defineProps<{
    roles: string[];
}>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Add User')"
            :breadcrumbs="[
                { title: t('Users'), href: index() },
                { title: t('Add User') },
            ]"
        />

        <UserForm :roles="roles" />
    </div>
</template>
