<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
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

const entries = computed(() => Object.entries(props.log.changes ?? {}))
const tone = computed(() => {
    const e = props.log.event
    return e.endsWith('failed') || e.endsWith('deleted') ? 'danger' : e.startsWith('auth') ? 'neutral' : e.endsWith('created') ? 'success' : 'info'
})
const show = v => (v === null || v === undefined || v === '' ? '(empty)' : typeof v === 'boolean' ? (v ? 'Yes' : 'No') : typeof v === 'object' ? JSON.stringify(v) : String(v))
const pretty = computed(() => JSON.stringify(props.log.meta ?? {}, null, 2))
</script>

<template>
    <FormPage :title="`Log #${log.id}`" :description="log.event_label" back="/settings/audit-log"
        back-label="Audit Log">
        <template #badge>
            <StatusBadge :tone="tone">{{ log.event_label }}</StatusBadge>
        </template>
        <template #actions>
            <Link v-if="log.user_id" :href="`/settings/audit-log?user=${log.user_id}`" class="btn">More from this user
            </Link>
        </template>

        <FormCard>
            <FormSection title="Event">
                <FormGrid>
                    <ReadField label="When" :value="dateTime(log.when, true)" />
                    <ReadField label="User" :value="log.user || 'System / not signed in'" />
                    <ReadField label="Event" :value="log.event_label" />
                    <ReadField label="Subject">
                        <Link v-if="log.subject_url" :href="log.subject_url" class="text-accent-dark hover:underline">{{
                            log.subject }}</Link>
                        <template v-else>{{ log.subject || '-' }}</template>
                    </ReadField>
                    <ReadField label="IP address" :value="log.ip || '-'" />
                    <ReadField label="Browser" :value="log.user_agent || '-'" />
                </FormGrid>
            </FormSection>

            <FormSection title="Changes">
                <p v-if="!entries.length" class="text-muted">No field-level changes were recorded for this entry.</p>
                <table v-else class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[12px] text-muted">
                            <th class="w-48 py-1.5 pr-3 font-medium">Field</th>
                            <th class="py-1.5 pr-3 font-medium">Before</th>
                            <th class="py-1.5 font-medium">After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="[field, c] in entries" :key="field"
                            class="border-b border-line/60 align-top last:border-b-0">
                            <th scope="row" class="py-2 pr-3 text-left font-medium">{{ field }}</th>
                            <template v-if="'added' in c">
                                <td class="py-2 pr-3">
                                    <span v-for="r in c.removed" :key="r"
                                        class="mb-1 mr-1 inline-block rounded bg-red-100 px-1.5 py-px text-[12px] text-red-800">-
                                        {{ r }}</span>
                                    <span v-if="!c.removed.length" class="text-muted">-</span>
                                </td>
                                <td class="py-2">
                                    <span v-for="a in c.added" :key="a"
                                        class="mb-1 mr-1 inline-block rounded bg-green-100 px-1.5 py-px text-[12px] text-green-800">+
                                        {{ a }}</span>
                                    <span v-if="!c.added.length" class="text-muted">-</span>
                                </td>
                            </template>
                            <template v-else>
                                <td class="py-2 pr-3 text-red-800">{{ show(c.from) }}</td>
                                <td class="py-2 text-green-800">{{ show(c.to) }}</td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </FormSection>

            <FormSection v-if="log.meta" title="Raw details" collapsible collapsed>
                <pre class="overflow-x-auto rounded-md bg-bg p-3 text-[12px]">{{ pretty }}</pre>
            </FormSection>
        </FormCard>
    </FormPage>
</template>