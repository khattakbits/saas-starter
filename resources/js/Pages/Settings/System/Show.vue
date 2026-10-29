<script setup>
import { useForm } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'

defineOptions({ layout: AppShell })
const props = defineProps({ settings: Object, canEdit: Boolean, timezones: Array })

const form = useForm({ ...props.settings })
const pageSizes = [20, 100, 500, 2500].map(n => ({ value: String(n), label: `${n} rows` }))

const save = () => form.put('/settings/system', { preserveScroll: true, onSuccess: () => form.defaults() })
</script>

<template>
    <FormPage title="System Settings" description="Settings that apply to the whole workspace">
        <template #badge>
            <StatusBadge v-if="form.isDirty" tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button v-if="form.isDirty" type="button" class="btn" @click="form.reset()">Discard</button>
            <button v-if="canEdit" type="submit" form="system-form" class="btn btn-primary"
                :disabled="form.processing || !form.isDirty">Save</button>
        </template>

        <form id="system-form" @submit.prevent="save">
            <FormCard>
                <FormSection title="Workspace">
                    <FormGrid>
                        <Field label="Workspace name" required hint="Shown in the top bar" :error="form.errors.app_name"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.app_name" :disabled="!canEdit" :invalid="invalid" />
                        </Field>
                        <Field label="Support email" :error="form.errors.support_email" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.support_email" type="email" :disabled="!canEdit"
                                :invalid="invalid" />
                        </Field>
                    </FormGrid>
                </FormSection>

                <FormSection title="Lists">
                    <FormGrid>
                        <Field label="Default rows per page" hint="Used when a list is opened"
                            :error="form.errors.default_page_size" v-slot="{ id }">
                            <Select :id="id" v-model="form.default_page_size" :options="pageSizes"
                                :disabled="!canEdit" />
                        </Field>
                    </FormGrid>
                </FormSection>
                <FormSection title="Data retention" icon="trash">
                    <FormGrid :cols="3">
                        <Field label="Keep deleted documents for" size="md"
                            hint="After this, the Recycle Bin empties itself" :error="form.errors.recycle_bin_days"
                            v-slot="{ id }">
                            <Select :id="id" v-model="form.recycle_bin_days" :disabled="!canEdit"
                                :options="[{ value: '7', label: '7 days' }, { value: '30', label: '30 days' }, { value: '90', label: '90 days' }, { value: '365', label: '1 year' }, { value: '0', label: 'Never' }]" />
                        </Field>
                    </FormGrid>
                </FormSection>
                <FormSection title="Regional">
                    <FormGrid>
                        <Field label="Display timezone" hint="Times are stored in UTC and shown in this timezone"
                            :error="form.errors.timezone" v-slot="{ id }">
                            <Select :id="id" v-model="form.timezone" :options="timezones" :disabled="!canEdit" />
                        </Field>
                    </FormGrid>
                </FormSection>
            </FormCard>
        </form>
    </FormPage>
</template>