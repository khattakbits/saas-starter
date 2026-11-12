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
import Checkbox from '@core/ui/Checkbox.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'

defineOptions({ layout: AppShell })
const props = defineProps({ settings: Object, mailReady: Boolean, canEdit: Boolean })

const form = useForm({ ...props.settings })

const retention = [
    { value: '30', label: '30 days' },
    { value: '90', label: '90 days' },
    { value: '365', label: '1 year' },
    { value: '0', label: 'Never' },
]

const save = () => form.put('/settings/email', { preserveScroll: true, onSuccess: () => form.defaults() })
</script>

<template>
    <FormPage title="Email Settings" description="How this workspace sends and keeps email">
        <template #badge>
            <StatusBadge :tone="mailReady ? 'success' : 'warning'">{{ mailReady ? 'Ready to send' : 'Not configured'
            }}</StatusBadge>
            <StatusBadge v-if="form.isDirty" tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button v-if="form.isDirty" type="button" class="btn" @click="form.reset()">Discard</button>
            <button v-if="canEdit && form.isDirty" type="submit" form="email-settings-form" class="btn btn-primary"
                :disabled="form.processing">Save</button>
        </template>

        <form id="email-settings-form" @submit.prevent="save">
            <FormCard>
                <FormSection title="Sending" icon="mail">
                    <div class="flex flex-col gap-2">
                        <Checkbox v-model="form.email_enabled" label="Email is enabled"
                            :disabled="!canEdit" />
                        <p class="text-[11px] text-muted">When off, no email is sent and attempts are logged as
                            failed.</p>
                    </div>
                    <FormGrid class="mt-4">
                        <Field label="Reply-to address" hint="Replies go here instead of the sender"
                            :error="form.errors.email_reply_to" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.email_reply_to" type="email" autocomplete="off"
                                :disabled="!canEdit" :invalid="invalid" />
                        </Field>
                        <Field label="Daily limit" size="sm" hint="0 = unlimited" :error="form.errors.email_daily_limit"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.email_daily_limit" type="number" min="0" max="100000"
                                :disabled="!canEdit" :invalid="invalid" />
                        </Field>
                    </FormGrid>
                </FormSection>

                <FormSection title="Footer" icon="file">
                    <FormGrid :cols="1">
                        <Field label="Footer text" hint="Added under every email, max 500 characters"
                            :error="form.errors.email_footer" v-slot="{ id, invalid }">
                            <textarea :id="id" v-model="form.email_footer" rows="3" maxlength="500"
                                class="field h-auto min-h-20 resize-y py-1.5" :disabled="!canEdit"
                                :aria-invalid="invalid || undefined"
                                :aria-describedby="invalid ? id + '-err' : undefined"></textarea>
                        </Field>
                    </FormGrid>
                </FormSection>

                <FormSection title="Retention" icon="trash">
                    <FormGrid>
                        <Field label="Keep email logs for" size="md" hint="Older entries are pruned every night"
                            :error="form.errors.email_log_days" v-slot="{ id }">
                            <Select :id="id" v-model="form.email_log_days" :options="retention"
                                :disabled="!canEdit" />
                        </Field>
                    </FormGrid>
                </FormSection>
            </FormCard>
        </form>
    </FormPage>
</template>
