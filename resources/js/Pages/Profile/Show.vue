<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import ReadField from '@core/ui/ReadField.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'

defineOptions({ layout: AppShell })
defineProps({ roles: Array })

const auth = usePage().props.auth
const form = useForm({ current_password: '', password: '', password_confirmation: '' })

const save = () => form.put('/profile/password', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
})
</script>

<template>
    <FormPage title="Profile" description="Your account and password">
        <template #badge>
            <StatusBadge v-if="form.recentlySuccessful" tone="success">Password updated</StatusBadge>
            <StatusBadge v-else-if="form.isDirty" tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button type="submit" form="password-form" class="btn btn-primary"
                :disabled="form.processing || !form.isDirty">Update password</button>
        </template>

        <FormCard>
            <FormSection title="Account">
                <FormGrid>
                    <ReadField label="Name" :value="auth.name" />
                    <ReadField label="Email" :value="auth.email" />
                    <ReadField label="Roles" :value="roles.length ? roles.join(', ') : 'None'" />
                </FormGrid>
            </FormSection>

            <FormSection title="Change password">
                <form id="password-form" @submit.prevent="save">
                    <FormGrid>
                        <Field label="Current password" required :error="form.errors.current_password"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.current_password" type="password"
                                autocomplete="current-password" :invalid="invalid" />
                        </Field>
                        <span></span>
                        <Field label="New password" required hint="Use at least 10 characters"
                            :error="form.errors.password" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.password" type="password" autocomplete="new-password"
                                :invalid="invalid" />
                        </Field>
                        <Field label="Confirm new password" required v-slot="{ id }">
                            <Input :id="id" v-model="form.password_confirmation" type="password"
                                autocomplete="new-password" />
                        </Field>
                    </FormGrid>
                </form>
            </FormSection>
        </FormCard>
    </FormPage>
</template>