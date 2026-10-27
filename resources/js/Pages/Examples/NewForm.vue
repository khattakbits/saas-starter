<script setup>
import { computed, reactive, ref, watch } from 'vue'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import DatePicker from '@core/ui/DatePicker.vue'
import Checkbox from '@core/ui/Checkbox.vue'
import LineItems from '@core/ui/LineItems.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'

defineOptions({ layout: AppShell })

const blank = { fbrType: '', fbrScenario: '', taxCategory: '' }
const form = reactive({
    series: 'SINV-.####', customer: '', taxId: '', date: '2026-10-27', time: '14:42:21', due: '2026-10-27',
    isTax: false, remarks: '', ...blank,
})

// Tax invoice nahi raha to uske section ka data bhi saaf (server ko bhejna hi nahi)
watch(() => form.isTax, on => { if (!on) Object.assign(form, blank) })

const seriesOptions = [{ value: 'SINV-.####', label: 'SINV-.####' }, { value: 'SINV-R-.####', label: 'SINV-R-.####' }]
const customerOptions = ['Siyalvi Traders', 'AB Traders', 'Ali & Co', 'Ahmad Foods Solution', 'Faizan Trader']
    .map(c => ({ value: c, label: c, description: 'All Customer Groups, All Territories' }))

const newRow = () => ({ item: '', qty: 0, rate: 0 })
const items = ref([newRow()])
const columns = [
    { key: 'item', label: 'Item', width: '3fr' },
    { key: 'qty', label: 'Quantity', width: '1fr', align: 'right', type: 'number' },
    { key: 'rate', label: 'Rate (PKR)', width: '1fr', align: 'right', type: 'number' },
    { key: 'amount', label: 'Amount (PKR)', width: '1fr', align: 'right' },
]

const fmt = n => n.toLocaleString('en-US', { minimumFractionDigits: 2 })
const amount = r => (Number(r.qty) || 0) * (Number(r.rate) || 0)
const totalQty = computed(() => items.value.reduce((s, r) => s + (Number(r.qty) || 0), 0))
const total = computed(() => items.value.reduce((s, r) => s + amount(r), 0))
</script>

<template>
    <FormPage title="New Sales Invoice" back="/examples/list" back-label="Payment Entry">
        <template #badge>
            <StatusBadge tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button type="button" class="btn">Discard</button>
            <button type="button" class="btn btn-primary">Save</button>
        </template>

        <FormCard>
            <!-- hamesha dikhne wala -->
            <FormSection>
                <FormGrid>
                    <Field label="Series" required v-slot="{ id }"><Select :id="id" v-model="form.series"
                            :options="seriesOptions" /></Field>
                    <Field label="Date" required v-slot="{ id }">
                        <DatePicker :id="id" v-model="form.date" />
                    </Field>
                    <Field label="Customer" required v-slot="{ id }">
                        <Select :id="id" v-model="form.customer" :options="customerOptions"
                            placeholder="Select customer" />
                    </Field>
                    <Field label="Posting Time" v-slot="{ id }"><Input :id="id" v-model="form.time" /></Field>
                    <Field label="Tax Id" v-slot="{ id }"><Input :id="id" v-model="form.taxId" /></Field>
                    <Field label="Payment Due Date" required v-slot="{ id }">
                        <DatePicker :id="id" v-model="form.due" />
                    </Field>
                    <div class="col-span-2">
                        <Checkbox v-model="form.isTax" label="Is Tax Invoice" />
                    </div>
                </FormGrid>
            </FormSection>

            <!-- conditional: sirf Tax Invoice mein, warna hai hi nahi -->
            <FormSection title="FBR Digital Invoicing" :when="form.isTax">
                <FormGrid>
                    <Field label="Invoice Type" required v-slot="{ id }"><Input :id="id" v-model="form.fbrType" />
                    </Field>
                    <Field label="Scenario" v-slot="{ id }"><Input :id="id" v-model="form.fbrScenario" /></Field>
                </FormGrid>
            </FormSection>

            <FormSection title="Items">
                <LineItems v-model="items" :columns="columns" :new-row="newRow">
                    <template #cell-amount="{ row }">
                        <span class="block w-full px-2 text-right font-medium tabular-nums">{{ fmt(amount(row))
                        }}</span>
                    </template>
                </LineItems>
                <div class="mt-4 grid grid-cols-2 gap-x-8">
                    <div class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-ink/80">Total Quantity</span>
                        <div class="min-h-7 rounded-md bg-bg px-2 py-1.5 tabular-nums">{{ totalQty }}</div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-ink/80">Total (PKR)</span>
                        <div class="min-h-7 rounded-md bg-bg px-2 py-1.5 tabular-nums">Rs {{ fmt(total) }}</div>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Taxes and Charges" :when="form.isTax">
                <FormGrid>
                    <Field label="Tax Category" v-slot="{ id }"><Input :id="id" v-model="form.taxCategory" /></Field>
                </FormGrid>
            </FormSection>

            <!-- collapsible: sab ke liye hai, magar kam kaam ka -->
            <FormSection title="Additional details" collapsible collapsed>
                <FormGrid :cols="1">
                    <Field label="Remarks" v-slot="{ id }"><Input :id="id" v-model="form.remarks" /></Field>
                </FormGrid>
            </FormSection>
        </FormCard>
    </FormPage>
</template>