<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import Checkbox from '@core/ui/Checkbox.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'

import { useFormat } from '@core/composables/useFormat'
import { useUnsavedGuard } from '@core/composables/useUnsavedGuard'
const { dateTime } = useFormat()
useUnsavedGuard(() => form.isDirty)

defineOptions({ layout: AppShell })
const props = defineProps({ user: Object, roles: Array })

const editing = computed(() => !!props.user)
const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    is_active: props.user?.is_active ?? true,
    roles: props.user?.roles ?? [],
})

function save() {
    const done = {
        preserveScroll: true,
        onSuccess: () => { form.reset('password', 'password_confirmation'); form.defaults() },
    }
    editing.value ? form.put(`/settings/users/${props.user.id}`, done) : form.post('/settings/users', done)
}
</script>

<template>
    <FormPage :title="editing ? user.name : 'New User'" back="/settings/users" back-label="Users"
        :description="editing ? `Last login: ${dateTime(user.last_login) || 'Never'}` : 'Create an account and choose what it can do'">
        <template #badge>
            <StatusBadge v-if="editing" :tone="user.is_active ? 'success' : 'neutral'">{{ user.is_active ? 'Active' :
                'Disabled' }}</StatusBadge>
            <StatusBadge v-if="!editing || form.isDirty" tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button v-if="form.isDirty" type="button" class="btn" @click="form.reset()">Discard</button>
            <button type="submit" form="user-form" class="btn btn-primary"
                :disabled="form.processing || (editing && !form.isDirty)">Save</button>
        </template>

        <form id="user-form" @submit.prevent="save">
            <FormCard>
                <FormSection title="Account">
                    <FormGrid>
                        <Field label="Name" required :error="form.errors.name" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.name" autocomplete="off" :invalid="invalid" />
                        </Field>
                        <Field label="Email" required :error="form.errors.email" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.email" type="email" autocomplete="off" :invalid="invalid" />
                        </Field>
                        <Field :label="editing ? 'New password' : 'Password'" :required="!editing"
                            :hint="editing ? 'Leave blank to keep the current password' : 'At least 10 characters'"
                            :error="form.errors.password" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.password" type="password" autocomplete="new-password"
                                :invalid="invalid" />
                        </Field>
                        <Field label="Confirm password" :required="!editing" v-slot="{ id }">
                            <Input :id="id" v-model="form.password_confirmation" type="password"
                                autocomplete="new-password" />
                        </Field>
                    </FormGrid>
                    <div class="mt-4">
                        <Checkbox v-model="form.is_active" label="Active (can sign in)" :disabled="user?.is_self" />
                        <p v-if="user?.is_self" class="mt-1 text-[11px] text-muted">You cannot disable your own account.
                        </p>
                        <p v-if="form.errors.is_active" role="alert" class="mt-1 text-[11px] text-red-700">{{
                            form.errors.is_active }}</p>
                    </div>
                </FormSection>

                <FormSection title="Roles">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                        <label v-for="r in roles" :key="r.name" class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.roles" type="checkbox" :value="r.name"
                                class="size-3.5 accent-accent" />
                            <span class="font-medium">{{ r.name }}</span>
                            <span class="text-[11px] text-muted">{{ r.permissions }} permissions</span>
                        </label>
                    </div>
                    <p v-if="!roles.length" class="text-muted">No roles yet. Create one in Settings, Roles.</p>
                    <p v-if="form.errors.roles" role="alert" class="mt-2 text-[11px] text-red-700">{{ form.errors.roles
                        }}</p>
                </FormSection>
            </FormCard>
        </form>
    </FormPage>
</template>