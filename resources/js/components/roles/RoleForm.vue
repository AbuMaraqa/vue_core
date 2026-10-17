<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, store, update } from '@/routes/admin/roles';
import type { PermissionGroup, Role } from '@/types';

const props = defineProps<{
    role?: Role;
    permissionGroups: PermissionGroup[];
}>();

const { t } = useTranslations();

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ?? [],
});

const allPermissions = computed(() =>
    props.permissionGroups.flatMap((group) =>
        group.permissions.map((permission) => permission.name),
    ),
);

const namesOf = (group: PermissionGroup): string[] =>
    group.permissions.map((permission) => permission.name);

const selectedCount = (names: string[]): number =>
    names.filter((name) => form.permissions.includes(name)).length;

function toggleAll(names: string[], selected: boolean): void {
    form.permissions = selected
        ? [...new Set([...form.permissions, ...names])]
        : form.permissions.filter((name) => !names.includes(name));
}

function submit(): void {
    form.submit(props.role ? update(props.role.id) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <BaseCard :title="t('Role details')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <BaseLabel for="name" required>{{
                        t('Role name')
                    }}</BaseLabel>
                    <BaseInput
                        id="name"
                        v-model="form.name"
                        :disabled="role?.is_protected"
                        :invalid="Boolean(form.errors.name)"
                    />
                    <BaseInputError :message="form.errors.name" />
                    <p
                        v-if="role?.is_protected"
                        class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ t('System roles cannot be renamed.') }}
                    </p>
                </div>
            </div>
        </BaseCard>

        <BaseCard
            :title="t('Permissions')"
            :description="
                t('Choose what members of this role are allowed to do.')
            "
        >
            <template #actions>
                <BaseCheckbox
                    :model-value="
                        selectedCount(allPermissions) === allPermissions.length
                    "
                    :indeterminate="
                        selectedCount(allPermissions) > 0 &&
                        selectedCount(allPermissions) < allPermissions.length
                    "
                    @update:model-value="
                        (value) => toggleAll(allPermissions, Boolean(value))
                    "
                >
                    {{ t('Select all permissions') }}
                </BaseCheckbox>
            </template>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="group in permissionGroups"
                    :key="group.key"
                    class="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                >
                    <div
                        class="mb-4 flex items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800"
                    >
                        <BaseCheckbox
                            :model-value="
                                selectedCount(namesOf(group)) ===
                                group.permissions.length
                            "
                            :indeterminate="
                                selectedCount(namesOf(group)) > 0 &&
                                selectedCount(namesOf(group)) <
                                    group.permissions.length
                            "
                            @update:model-value="
                                (value) =>
                                    toggleAll(namesOf(group), Boolean(value))
                            "
                        >
                            <span
                                class="font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ t(group.label) }}
                            </span>
                        </BaseCheckbox>
                        <span class="text-theme-xs text-gray-400">
                            {{ selectedCount(namesOf(group)) }}/{{
                                group.permissions.length
                            }}
                        </span>
                    </div>
                    <div class="space-y-3">
                        <BaseCheckbox
                            v-for="permission in group.permissions"
                            :key="permission.name"
                            v-model="form.permissions"
                            :value="permission.name"
                        >
                            {{ t(permission.label) }}
                        </BaseCheckbox>
                    </div>
                </div>
            </div>
            <BaseInputError :message="form.errors.permissions" />
        </BaseCard>

        <div class="flex justify-end gap-3">
            <BaseButton variant="outline" :href="index()">
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ role ? t('Save changes') : t('Create role') }}
            </BaseButton>
        </div>
    </form>
</template>
