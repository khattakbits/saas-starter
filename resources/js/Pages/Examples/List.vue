<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import ListView from '@core/ui/ListView.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import Icon from '@core/ui/Icon.vue'
import { useCan } from '@core/composables/useCan'

defineOptions({ layout: AppShell })
const can = useCan()

const names = ['Unique Enterprises (Johar Town)', 'AB Traders', 'Ali & Co', 'Ahmad Foods Solution', 'Siyalvi Traders', 'Faizan Trader', 'Aafie Traders']
const all = ref(Array.from({ length: 150 }, (_, i) => ({
    id: `BRV-${String(1574 - i).padStart(5, '0')}`,
    title: names[i % names.length],
    status: i % 4 === 1 ? 'Cancelled' : 'Submitted',
    type: i % 7 === 0 ? 'Pay' : 'Receive',
    date: `${String((i % 28) + 1).padStart(2, '0')}-09-2026`,
    mode: i % 3 === 0 ? 'Cash' : '',
    age: i < 18 ? '2 h' : `${Math.floor(i / 6)} d`,
    comments: i % 9 === 0 ? 2 : 0,
})))

const columns = [
    { key: 'title', label: 'Title', width: '2.2fr' },
    { key: 'status', label: 'Status', width: '1fr' },
    { key: 'type', label: 'Payment Type', width: '1fr' },
    { key: 'date', label: 'Posting Date', width: '1fr' },
    { key: 'mode', label: 'Mode of Payment', width: '1fr' },
    { key: 'id', label: 'ID', width: '1fr' },
]

const stateOptions = [
    { value: 'data', label: 'Demo: with data' }, { value: 'loading', label: 'Demo: loading' },
    { value: 'empty', label: 'Demo: no records yet' }, { value: 'error', label: 'Demo: load error' },
]
const statusOptions = [{ value: 'Submitted', label: 'Submitted' }, { value: 'Cancelled', label: 'Cancelled' }]
const typeOptions = [{ value: 'Receive', label: 'Receive' }, { value: 'Pay', label: 'Pay' }]

const state = ref('data')
const fId = ref('')
const fTitle = ref('')
const fStatus = ref('')
const fType = ref('')
const pageSize = ref(20)
const limit = ref(20)

const isFiltered = computed(() => !!(fId.value || fTitle.value || fStatus.value || fType.value))
const filtered = computed(() => all.value.filter(r =>
    (!fId.value || r.id.toLowerCase().includes(fId.value.toLowerCase())) &&
    (!fTitle.value || r.title.toLowerCase().includes(fTitle.value.toLowerCase())) &&
    (!fStatus.value || r.status === fStatus.value) &&
    (!fType.value || r.type === fType.value)))
const shown = computed(() => (state.value === 'data' ? filtered.value.slice(0, limit.value) : []))

watch(pageSize, v => (limit.value = v))
watch([fId, fTitle, fStatus, fType], () => (limit.value = pageSize.value))

function clearFilters() { fId.value = fTitle.value = fStatus.value = fType.value = '' }

/* ---------- bulk delete ---------- */
const confirmOpen = ref(false)
const pendingIds = ref([])
// Submitted records delete nahi ho sakte, pehle Cancel karne parte hain
const deletable = computed(() => all.value.filter(r => pendingIds.value.includes(r.id) && r.status !== 'Submitted'))
const skipped = computed(() => pendingIds.value.length - deletable.value.length)

function askDelete(ids) { pendingIds.value = ids; confirmOpen.value = true }
function doDelete() {
    const gone = new Set(deletable.value.map(r => r.id))
    all.value = all.value.filter(r => !gone.has(r.id))
    confirmOpen.value = false
}
</script>

<template>
    <ListPage title="Payment Entry" description="List: filters, header and footer stay put, only the rows scroll">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh">
                <Icon name="refresh" :size="13" />
            </button>
            <Link href="/examples/new-form" class="btn btn-primary">
                <Icon name="plus" :size="13" class="mr-1" />Add Payment Entry
            </Link>
        </template>

        <ListView :columns="columns" :rows="shown" :total="state === 'data' ? filtered.length : 0"
            :loading="state === 'loading'" :error="state === 'error'" :filtered="isFiltered"
            :has-more="state === 'data' && filtered.length > limit" v-model:page-size="pageSize"
            @load-more="limit += pageSize" @clear-filters="clearFilters" @retry="state = 'data'"
            @row-click="router.visit('/examples/submitted')">
            <template #filters>
                <Input v-model="fId" type="search" placeholder="ID" aria-label="Filter by ID" class="w-36" />
                <Input v-model="fTitle" type="search" placeholder="Title" aria-label="Filter by title" class="w-44" />
                <div class="w-36"><Select v-model="fStatus" :options="statusOptions" placeholder="Status" clearable />
                </div>
                <div class="w-40"><Select v-model="fType" :options="typeOptions" placeholder="Payment Type" clearable />
                </div>
            </template>
            <template #filter-actions>
                <button v-if="isFiltered" type="button" class="btn" @click="clearFilters">Clear filters</button>
                <div class="w-44"><Select v-model="state" :options="stateOptions" /></div>
            </template>

            <template #cell-title="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-status="{ value }">
                <StatusBadge :tone="value === 'Cancelled' ? 'danger' : 'info'">{{ value }}</StatusBadge>
            </template>
            <template #cell-type="{ value }">
                <StatusBadge>{{ value }}</StatusBadge>
            </template>
            <template #row-end="{ row }">
                <span>{{ row.age }}</span>
                <span class="flex items-center gap-1">
                    <Icon name="file" :size="11" />{{ row.comments }}
                </span>
            </template>
            <template #bulk="{ ids }">
                <button type="button" class="btn">Export {{ ids.length }}</button>
                <button v-if="can('demo.delete')" type="button" class="btn btn-danger"
                    @click="askDelete(ids)">Delete</button>
            </template>
            <template #empty-actions>
                <Link href="/examples/new-form" class="btn btn-primary">Add Payment Entry</Link>
            </template>
        </ListView>

        <ConfirmDialog v-model="confirmOpen" title="Delete records?" confirm-label="Delete" danger
            :disabled="!deletable.length" @confirm="doDelete">
            <p v-if="deletable.length"><strong>{{ deletable.length }}</strong> record(s) will be deleted. This cannot be
                undone.
            </p>
            <p v-else>None of the selected records can be deleted.</p>
            <p v-if="skipped" class="mt-2 text-muted">{{ skipped }} Submitted record(s) will be skipped. Cancel them
                first.</p>
        </ConfirmDialog>
    </ListPage>
</template>