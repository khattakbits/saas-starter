<script setup>
import { computed } from 'vue'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import ReadField from '@core/ui/ReadField.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import { useFormat } from '@core/composables/useFormat'

defineOptions({ layout: AppShell })
const props = defineProps({ log: Object })
const { dateTime } = useFormat()

const tone = computed(() => ({ sent: 'success', failed: 'danger', queued: 'warning' })[props.log.status] ?? 'neutral')
</script>

<template>
    <FormPage :title="log.subject" :description="`To ${log.to}`" back="/settings/email-log" back-label="Email Log">
        <template #badge>
            <StatusBadge :tone="tone">{{ log.status }}</StatusBadge>
        </template>

        <FormCard>
            <FormSection title="Delivery">
                <FormGrid>
                    <ReadField label="To" :value="log.to" />
                    <ReadField label="Subject" :value="log.subject" />
                    <ReadField label="Template" :value="log.template || '-'" />
                    <ReadField label="Account" :value="log.account || 'Framework default mailer'" />
                    <ReadField label="Status" :value="log.status" />
                    <ReadField label="Attempts" :value="log.attempts" />
                    <ReadField label="Queued" :value="dateTime(log.queued_at, true) || '-'" />
                    <ReadField label="Sent" :value="dateTime(log.sent_at, true) || '-'" />
                    <ReadField label="Logged" :value="dateTime(log.when, true)" />
                    <ReadField label="Log ID" :value="`#${log.id}`" />
                </FormGrid>
            </FormSection>

            <FormSection title="Error" :when="!!log.error">
                <pre
                    class="noscroll max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-md bg-bg px-2.5 py-1.5 text-[12px] text-muted">{{ log.error }}</pre>
            </FormSection>
        </FormCard>
    </FormPage>
</template>
