<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import ListView from '@core/ui/ListView.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import DocStatusBadge from '@core/ui/DocStatusBadge.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import RowAction from '@core/ui/RowAction.vue'
import Icon from '@core/ui/Icon.vue'
import { useCan } from '@core/composables/useCan'
import { useServerList } from '@core/composables/useServerList'
import { useRowDelete } from '@core/composables/useRowDelete'
import { useFormat } from '@core/composables/useFormat'

defineOptions({ layout: AppShell })
const can = useCan()
const { date, money } = useFormat()

const list = useServerList({ endpoint: '/examples/entries/data', sort: { key: 'id', dir: 'desc' }, filters: { status: '' } })
const del = reactive(useRowDelete({ url: r => `/examples/entries/${r.id}`, onDone: () => list.refresh() }))

const columns = [
    { key: 'name', label: 'Number', width: '1.2fr', sort: 'name' },
    { key: 'party', label: 'Party', width: '2fr', sort: 'party' },
    { key: 'posting_date', label: 'Posting date', width: '1fr', sort: 'date' },
    { key: 'amount', label: 'Amount (PKR)', width: '1fr', align: 'right', sort: 'amount' },
    { key: 'docstatus', label: 'Status', width: '1fr', sort: 'status' },
]
const statusOptions = [{ value: '0', label: 'Draft' }, { value: '1', label: 'Submitted' }, { value: '2', label: 'Cancelled' }]

/* bulk delete */
const bulkOpen = ref(false)
const bulk = ref({ ids: [], clear: null })
const locked = computed(() => list.rows.filter(r => bulk.value.ids.includes(r.id) && !r.deletable).length)
const movable = computed(() => bulk.value.ids.length - locked.value)

const askBulk = (ids, clear) => { bulk.value = { ids, clear }; bulkOpen.value = true }
function runBulk() {
    router.post('/examples/entries/bulk-delete', { ids: bulk.value.ids }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { bulk.value.clear?.(); list.refresh() },
        onFinish: () => (bulkOpen.value = false),
    })
}
</script>

<template>
    <ListPage title="Demo Entries" description="A sample document: Draft, Submit, Cancel, Amend, Recycle Bin">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="16" />
            </button>
            <Link v-if="can('examples.entries.create')" href="/examples/entries/create" class="btn btn-primary">
                <Icon name="plus" :size="16" />Add Entry
            </Link>
        </template>

        <ListView :columns="columns" :rows="list.rows" :total="list.total" :loading="list.loading" :error="list.error"
            label="Demo entries" :filtered="list.isFiltered" :has-more="list.hasMore" :loading-more="list.loadingMore"
            v-model:page-size="list.pageSize" v-model:sort="list.sort" :reset-key="list.resetKey"
            :selectable="can('examples.entries.delete')" @load-more="list.loadMore()" @retry="list.retry()"
            @clear-filters="list.clearFilters()" @row-click="row => router.visit(`/examples/entries/${row.id}`)">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search number or party"
                    aria-label="Search entries" class="w-56" />
                <div class="w-40"><Select v-model="list.filters.status" :options="statusOptions" placeholder="Status"
                        clearable /></div>
            </template>
            <template #filter-actions>
                <button v-if="list.isFiltered" type="button" class="btn" @click="list.clearFilters()">Clear
                    filters</button>
            </template>

            <template #cell-name="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-posting_date="{ value }">{{ date(value) }}</template>
            <template #cell-amount="{ value }"><span class="tabular-nums">{{ money(value) }}</span></template>
            <template #cell-docstatus="{ value }">
                <DocStatusBadge :status="value" />
            </template>

            <template #row-end="{ row }">
                <RowAction v-if="row.deletable && can('examples.entries.delete')" :label="`Delete ${row.name}`" danger
                    @click="del.ask(row)" />
            </template>
            <template #bulk="{ ids, clear }">
                <button type="button" class="btn btn-danger" @click="askBulk(ids, clear)">Delete</button>
            </template>
            <template #empty-actions>
                <Link v-if="can('examples.entries.create')" href="/examples/entries/create" class="btn btn-primary">Add
                    Entry</Link>
            </template>
        </ListView>

        <ConfirmDialog v-model="del.open" title="Move to Recycle Bin?" confirm-label="Delete" danger
            :disabled="del.busy" @confirm="del.confirm">
            <p><strong>{{ del.target?.name }}</strong> will be moved to the Recycle Bin. You can restore it from there
                as a
                Draft.</p>
        </ConfirmDialog>

        <ConfirmDialog v-model="bulkOpen" title="Move to Recycle Bin?" confirm-label="Delete" danger
            :disabled="!movable" @confirm="runBulk">
            <p v-if="movable"><strong>{{ movable }}</strong> document(s) will be moved to the Recycle Bin and can be
                restored as
                Drafts.</p>
            <p v-else>None of the selected documents can be deleted.</p>
            <p v-if="locked" class="mt-2 text-muted">{{ locked }} Submitted document(s) will be skipped. Cancel them
                first.</p>
        </ConfirmDialog>
    </ListPage>
</template>