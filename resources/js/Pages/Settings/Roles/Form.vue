<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import PermissionMatrix from '@core/ui/PermissionMatrix.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import { useCan } from '@core/composables/useCan'

defineOptions({ layout: AppShell })
const props = defineProps({ role: Object, groups: Array, granted: Array })

const can = useCan()
const editing = computed(() => !!props.role)
const form = useForm({ name: props.role?.name ?? '', permissions: [...props.granted] })
const deleting = ref(false)
const canDelete = computed(() => editing.value && !props.role.system && props.role.users === 0 && can('settings.roles.delete'))

function save() {
    const done = {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    }
    editing.value ? form.put(`/settings/roles/${props.role.id}`, done) : form.post('/settings/roles', done)
}

const remove = () => router.delete(`/settings/roles/${props.role.id}`, { onFinish: () => (deleting.value = false) })
</script>

<template>
    <FormPage :title="editing ? role.name : 'New Role'" back="/settings/roles" back-label="Roles"
        :description="editing ? `${role.users} user(s) have this role` : 'Choose what this role is allowed to do'">
        <template #badge>
            <StatusBadge v-if="editing && !form.isDirty" tone="neutral">{{ form.permissions.length }} permissions
            </StatusBadge>
            <StatusBadge v-if="!editing || form.isDirty" tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button v-if="canDelete" type="button" class="btn btn-danger" @click="deleting = true">Delete</button>
            <button v-if="form.isDirty" type="button" class="btn" @click="form.reset()">Discard</button>
            <button v-if="!role?.system" type="submit" form="role-form" class="btn btn-primary"
                :disabled="form.processing || (editing && !form.isDirty)">Save</button>
        </template>

        <form id="role-form" @submit.prevent="save">
            <FormCard>
                <FormSection title="Role">
                    <FormGrid>
                        <Field label="Name" required hint="Letters, numbers, spaces, - and _" :error="form.errors.name"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.name" autocomplete="off" :invalid="invalid" />
                        </Field>
                    </FormGrid>
                </FormSection>

                <FormSection title="Permissions">
                    <p v-if="role?.system" class="text-muted">Super admin has full access to everything. Its permissions
                        cannot
                        be edited.</p>
                    <template v-else>
                        <p class="mb-3 text-muted">Granting create, update or delete also grants view. Removing view
                            removes the rest.</p>
                        <PermissionMatrix v-model="form.permissions" :groups="groups" />
                        <p v-if="form.errors.permissions" role="alert" class="mt-2 text-[11px] text-red-700">{{
                            form.errors.permissions }}</p>
                    </template>
                </FormSection>
            </FormCard>
        </form>

        <ConfirmDialog v-model="deleting" title="Delete role?" confirm-label="Delete" danger @confirm="remove">
            <p>Role <strong>{{ role?.name }}</strong> will be deleted. This cannot be undone.</p>
        </ConfirmDialog>
    </FormPage>
</template>