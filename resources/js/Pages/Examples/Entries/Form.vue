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
import DatePicker from '@core/ui/DatePicker.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import DocStatusBadge from '@core/ui/DocStatusBadge.vue'
import SideSection from '@core/ui/SideSection.vue'
import FileAttachments from '@core/ui/FileAttachments.vue'
import DocumentActivity from '@core/ui/DocumentActivity.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import { useCan } from '@core/composables/useCan'
import { useUnsavedGuard } from '@core/composables/useUnsavedGuard'

defineOptions({ layout: AppShell })
const props = defineProps({ entry: Object })
const can = useCan()

const editing = computed(() => !!props.entry)
const form = useForm({
    party: props.entry?.party ?? '',
    amount: props.entry?.amount ?? '',
    posting_date: props.entry?.posting_date ?? new Date().toLocaleDateString('en-CA'),
    remarks: props.entry?.remarks ?? '',
})
useUnsavedGuard(() => form.isDirty)

// Lifecycle action ke baad activity dobara load ho (docstatus/updated_at badalta hai)
const activityKey = computed(() => `${props.entry?.docstatus}:${props.entry?.updated_at ?? ''}`)

const submitOpen = ref(false)
const deleteOpen = ref(false)

function save() {
    if (editing.value) {
        form.put(`/examples/entries/${props.entry.id}`, { preserveScroll: true, preserveState: true, onSuccess: () => form.defaults() })
    } else {
        form.post('/examples/entries')
    }
}
const submitDoc = () => router.post(`/examples/entries/${props.entry.id}/submit`, {}, { onFinish: () => (submitOpen.value = false) })
const remove = () => router.delete(`/examples/entries/${props.entry.id}`, { onFinish: () => (deleteOpen.value = false) })
</script>

<template>
    <FormPage :title="editing ? entry.name : 'New Demo Entry'" back="/examples/entries" back-label="Demo Entries"
        :description="editing ? 'Draft. Submit it when it is final' : 'Fill in the details and save'">
        <template #badge>
            <StatusBadge v-if="!editing || form.isDirty" tone="warning">Not Saved</StatusBadge>
            <DocStatusBadge v-else :status="0" />
        </template>
        <template #actions>
            <button v-if="editing && !form.isDirty && can('examples.entries.delete')" type="button"
                class="btn btn-danger" @click="deleteOpen = true">Delete</button>
            <button v-if="editing && form.isDirty" type="button" class="btn" @click="form.reset()">Discard</button>
            <button v-if="!editing || form.isDirty" type="submit" form="entry-form" class="btn btn-primary"
                :disabled="form.processing">
                {{ form.processing ? 'Saving...' : 'Save' }}
            </button>
            <button v-if="editing && !form.isDirty && can('examples.entries.submit')" type="button"
                class="btn btn-primary" @click="submitOpen = true">Submit</button>
        </template>

        <template #side>
            <SideSection title="Attachments" icon="paperclip">
                <FileAttachments v-if="editing" type="demo_entry" :id="entry.id" />
                <p v-else class="text-muted">Save the entry first to attach files.</p>
            </SideSection>
            <SideSection v-if="editing" title="Activity" icon="clock">
                <DocumentActivity type="demo_entry" :id="entry.id" :reload-key="activityKey" />
            </SideSection>
        </template>

        <form id="entry-form" @submit.prevent="save">
            <FormCard>
                <FormSection title="Entry" icon="file">
                    <FormGrid :cols="3">
                        <Field label="Party" required :span="2" :error="form.errors.party" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.party" autocomplete="off" :invalid="invalid" />
                        </Field>
                        <Field label="Posting date" required size="sm" :error="form.errors.posting_date"
                            v-slot="{ id, invalid }">
                            <DatePicker :id="id" v-model="form.posting_date" :invalid="invalid" />
                        </Field>
                        <Field label="Amount (PKR)" required size="sm" :error="form.errors.amount"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.amount" type="number" step="0.01" min="0" class="text-right"
                                :invalid="invalid" />
                        </Field>
                        <Field label="Remarks" :span="3" :error="form.errors.remarks" v-slot="{ id }">
                            <Input :id="id" v-model="form.remarks" />
                        </Field>
                    </FormGrid>
                </FormSection>
            </FormCard>
        </form>

        <ConfirmDialog v-model="submitOpen" title="Submit this document?" confirm-label="Submit" @confirm="submitDoc">
            <p>Once submitted, <strong>{{ entry?.name }}</strong> can no longer be edited. You can still cancel it and
                amend a
                copy.</p>
        </ConfirmDialog>
        <ConfirmDialog v-model="deleteOpen" title="Move to Recycle Bin?" confirm-label="Delete" danger
            @confirm="remove">
            <p><strong>{{ entry?.name }}</strong> will be moved to the Recycle Bin. You can restore it from there as a
                Draft.
            </p>
        </ConfirmDialog>
    </FormPage>
</template>