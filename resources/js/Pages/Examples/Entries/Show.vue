<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import ReadField from '@core/ui/ReadField.vue'
import DocStatusBadge from '@core/ui/DocStatusBadge.vue'
import SideSection from '@core/ui/SideSection.vue'
import FileAttachments from '@core/ui/FileAttachments.vue'
import DocumentActivity from '@core/ui/DocumentActivity.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import { useCan } from '@core/composables/useCan'
import { useFormat } from '@core/composables/useFormat'

defineOptions({ layout: AppShell })
const props = defineProps({ entry: Object })
const can = useCan()
const { date, dateTime, money } = useFormat()

const cancelOpen = ref(false)
const deleteOpen = ref(false)

const base = `/examples/entries/${props.entry.id}`
const cancelDoc = () => router.post(`${base}/cancel`, {}, { onFinish: () => (cancelOpen.value = false) })
const amend = () => router.post(`${base}/amend`)
const remove = () => router.delete(base, { onFinish: () => (deleteOpen.value = false) })

// Cancel/Amend ke baad activity dobara load ho (docstatus/updated_at badalta hai)
const activityKey = computed(() => `${props.entry.docstatus}:${props.entry.updated_at ?? ''}`)
</script>

<template>
    <FormPage :title="entry.name" back="/examples/entries" back-label="Demo Entries"
        :description="entry.docstatus === 1 ? `Submitted on ${dateTime(entry.submitted_at)}` : `Cancelled on ${dateTime(entry.cancelled_at)}`">
        <template #badge>
            <DocStatusBadge :status="entry.docstatus" />
        </template>
        <template #actions>
            <button v-if="entry.docstatus === 1 && can('examples.entries.cancel')" type="button" class="btn btn-danger"
                @click="cancelOpen = true">Cancel</button>
            <button v-if="entry.docstatus === 2 && !entry.amended_to && can('examples.entries.amend')" type="button"
                class="btn btn-primary" @click="amend">Amend</button>
            <button v-if="entry.docstatus === 2 && can('examples.entries.delete')" type="button" class="btn btn-danger"
                @click="deleteOpen = true">Delete</button>
        </template>

        <template #side>
            <SideSection title="Attachments" icon="paperclip">
                <FileAttachments type="demo_entry" :id="entry.id" :readonly="!can('examples.entries.update')" />
            </SideSection>
            <SideSection title="Activity" icon="clock">
                <DocumentActivity type="demo_entry" :id="entry.id" :reload-key="activityKey" />
            </SideSection>
            <SideSection v-if="entry.amended_from || entry.amended_to" title="Amendments" icon="file">
                <p v-if="entry.amended_from">Amended from <Link :href="`/examples/entries/${entry.amended_from.id}`"
                        class="text-accent-dark hover:underline">{{ entry.amended_from.name }}</Link>
                </p>
                <p v-if="entry.amended_to">Amended to <Link :href="`/examples/entries/${entry.amended_to.id}`"
                        class="text-accent-dark hover:underline">{{ entry.amended_to.name }}</Link>
                </p>
            </SideSection>
        </template>

        <FormCard>
            <FormSection title="Entry" icon="file">
                <FormGrid :cols="3">
                    <ReadField label="Party" :span="2" :value="entry.party" />
                    <ReadField label="Posting date" size="sm" :value="date(entry.posting_date)" />
                    <ReadField label="Amount (PKR)" size="sm" :value="money(entry.amount)" />
                    <ReadField label="Remarks" :span="3" :value="entry.remarks || '-'" />
                </FormGrid>
            </FormSection>
        </FormCard>

        <ConfirmDialog v-model="cancelOpen" title="Cancel this document?" confirm-label="Cancel document" danger
            @confirm="cancelDoc">
            <p><strong>{{ entry.name }}</strong> will be cancelled and stay read-only. You can amend a new Draft copy
                afterwards.</p>
        </ConfirmDialog>
        <ConfirmDialog v-model="deleteOpen" title="Move to Recycle Bin?" confirm-label="Delete" danger
            @confirm="remove">
            <p><strong>{{ entry.name }}</strong> will be moved to the Recycle Bin. You can restore it from there as a
                Draft.</p>
        </ConfirmDialog>
    </FormPage>
</template>