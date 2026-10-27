<script setup>
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import ReadField from '@core/ui/ReadField.vue'
import SideSection from '@core/ui/SideSection.vue'
import FileAttachments from '@core/ui/FileAttachments.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import Icon from '@core/ui/Icon.vue'

defineOptions({ layout: AppShell })
</script>

<template>
    <FormPage title="Unique Enterprises (Johar Town)" back="/examples/list" back-label="Payment Entry">
        <template #badge>
            <StatusBadge tone="info">Submitted</StatusBadge>
        </template>
        <template #actions>
            <button type="button" class="btn">Ledger</button>
            <button type="button" class="btn btn-icon" aria-label="Previous record">
                <Icon name="chevron-left" :size="13" />
            </button>
            <button type="button" class="btn btn-icon" aria-label="Next record">
                <Icon name="chevron-right" :size="13" />
            </button>
            <button type="button" class="btn btn-icon" aria-label="Print">
                <Icon name="print" :size="13" />
            </button>
            <button type="button" class="btn btn-icon" aria-label="More actions">
                <Icon name="more" :size="13" />
            </button>
            <button type="button" class="btn">Cancel</button>
        </template>

        <template #side>
            <SideSection title="Assigned To" icon="user">
                <button type="button"
                    class="flex size-7 items-center justify-center rounded-full border border-line bg-surface hover:bg-bg"
                    aria-label="Assign to someone">
                    <Icon name="plus" :size="13" />
                </button>
            </SideSection>
            <SideSection title="Attachments" icon="paperclip">
                <FileAttachments type="demo" :id="1" />
            </SideSection>
            <SideSection title="Shared With" icon="users">
                <button type="button"
                    class="flex size-7 items-center justify-center rounded-full border border-line bg-surface hover:bg-bg"
                    aria-label="Share with someone">
                    <Icon name="plus" :size="13" />
                </button>
            </SideSection>
            <SideSection title="Tags" icon="tag">
                <p class="text-muted">Add a tag ...</p>
            </SideSection>
            <section class="py-3 text-[11px] text-muted">
                <p><span class="font-semibold text-ink/80">You</span> edited this · 2 hours ago</p>
                <p class="mt-1"><span class="font-semibold text-ink/80">You</span> created this · 2 hours ago</p>
            </section>
        </template>

        <FormCard>
            <FormSection title="Connections" collapsible collapsed>
                <p class="text-muted">No linked documents yet.</p>
            </FormSection>
            <FormSection title="Type of Payment">
                <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                    <ReadField label="Payment Type" value="Receive" />
                    <ReadField label="Posting Date" value="08-09-2026" />
                </div>
            </FormSection>
            <FormSection title="Payment From / To">
                <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                    <ReadField label="Party" value="Unique Enterprises (Johar Town)" />
                    <ReadField label="Party Name" value="Unique Enterprises" />
                </div>
            </FormSection>
            <FormSection title="Accounts" collapsible collapsed>
                <p class="text-muted">Account details.</p>
            </FormSection>
            <FormSection title="Amount">
                <div class="grid grid-cols-2 gap-x-8">
                    <ReadField label="Paid Amount (PKR)" value="Rs 320,000.00" />
                </div>
            </FormSection>
            <FormSection title="Writeoff">
                <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                    <ReadField label="Total Allocated Amount (PKR)" value="Rs 0.00" />
                    <ReadField label="Unallocated Amount (PKR)" value="Rs 320,000.00" />
                    <span></span>
                    <ReadField label="Difference Amount (PKR)" value="Rs 0.00" />
                </div>
            </FormSection>
        </FormCard>
    </FormPage>
</template>