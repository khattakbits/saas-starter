<script setup>
import { ref } from 'vue'
import AppShell from '@core/layouts/AppShell.vue'
import ReportPage from '@core/layouts/ReportPage.vue'
import ReportGrid from '@core/ui/ReportGrid.vue'
import Field from '@core/ui/Field.vue'
import DatePicker from '@core/ui/DatePicker.vue'
import Select from '@core/ui/Select.vue'
import Icon from '@core/ui/Icon.vue'

defineOptions({ layout: AppShell })

const t0 = performance.now()
const customers = ['Aafie Traders', 'Ahmad Foods Solution', 'AB Traders', 'Ali & Co', 'Siyalvi Traders', 'Faizan Trader']
const rows = Array.from({ length: 60 }, (_, i) => {
    const net = ((i * 7919) % 180000) + 12000
    return {
        type: 'Sales Invoice', voucher: `SINV-${3729 - i}`,
        date: `${String((i % 28) + 1).padStart(2, '0')}-10-2026`,
        customer: customers[i % customers.length], group: 'Individual', territory: 'All Territories',
        taxId: String(7211278 - i * 137), account: 'Debtors - Main', owner: 'Administrator',
        net, grand: Math.round(net * 1.18 * 100) / 100,
    }
})
const execTime = ((performance.now() - t0) / 1000).toFixed(6)

const columns = [
    { key: 'type', label: 'Voucher Type', width: 120 },
    { key: 'voucher', label: 'Voucher', width: 110 },
    { key: 'date', label: 'Posting Date', width: 110 },
    { key: 'customer', label: 'Customer', width: 180 },
    { key: 'group', label: 'Customer Group', width: 130 },
    { key: 'territory', label: 'Territory', width: 120 },
    { key: 'taxId', label: 'Tax Id', width: 90 },
    { key: 'account', label: 'Receivable Account', width: 150 },
    { key: 'owner', label: 'Owner', width: 110 },
    { key: 'net', label: 'Net Total (PKR)', width: 140, align: 'right', type: 'money', total: true },
    { key: 'grand', label: 'Grand Total (PKR)', width: 140, align: 'right', type: 'money', total: true },
]

const customerOptions = customers.map(c => ({ value: c, label: c, description: 'All Customer Groups, All Territories' }))
const simple = list => list.map(v => ({ value: v, label: v }))

const from = ref('2026-09-26')
const to = ref('2026-10-26')
const customer = ref('')
const costCenter = ref('')
const warehouse = ref('')
const brand = ref('')
</script>

<template>
    <ReportPage title="Sales Register"
        description="Report: filters stay, the grid scrolls both ways, totals stay at the bottom">
        <template #actions>
            <button type="button" class="btn">Export</button>
            <button type="button" class="btn">Print</button>
            <button type="button" class="btn btn-icon" aria-label="Refresh">
                <Icon name="refresh" :size="13" />
            </button>
        </template>

        <template #filters>
            <div class="grid grid-cols-6 gap-3">
                <Field label="From date" v-slot="{ id }">
                    <DatePicker :id="id" v-model="from" />
                </Field>
                <Field label="To date" v-slot="{ id }">
                    <DatePicker :id="id" v-model="to" />
                </Field>
                <Field label="Customer" v-slot="{ id }"><Select :id="id" v-model="customer" :options="customerOptions"
                        placeholder="All" clearable /></Field>
                <Field label="Cost Center" v-slot="{ id }"><Select :id="id" v-model="costCenter"
                        :options="simple(['Main - HQ', 'Lahore Branch'])" placeholder="All" clearable /></Field>
                <Field label="Warehouse" v-slot="{ id }"><Select :id="id" v-model="warehouse"
                        :options="simple(['Stores - Main', 'Finished Goods'])" placeholder="All" clearable /></Field>
                <Field label="Brand" v-slot="{ id }"><Select :id="id" v-model="brand"
                        :options="simple(['Brand A', 'Brand B'])" placeholder="All" clearable /></Field>
            </div>
            <label class="mt-2.5 flex items-center gap-2"><input type="checkbox" class="size-3 accent-accent" /> Show
                Ledger View</label>
        </template>

        <ReportGrid :columns="columns" :rows="rows" />

        <template #footer>
            <span>For comparison, use &gt;5, &lt;10 or =324. For ranges, use 5:10 (values between 5 &amp; 10).</span>
            <span>Execution time: {{ execTime }} sec</span>
        </template>
    </ReportPage>
</template>