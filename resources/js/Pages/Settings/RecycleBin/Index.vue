<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import ListView from '@core/ui/ListView.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import RowAction from '@core/ui/RowAction.vue'
import Icon from '@core/ui/Icon.vue'
import { useCan } from '@core/composables/useCan'
import { useServerList } from '@core/composables/useServerList'
import { useFormat } from '@core/composables/useFormat'

defineOptions({ layout: AppShell })
const props = defineProps({ types: Array, days: Number })
const can = useCan()
const { dateTime } = useFormat()

const list = useServerList({
    endpoint: '/settings/recycle-bin/data',
    sort: { key: 'deleted', dir: 'desc' },
    filters: { type: '' },
})

const columns = [
    { key: 'title', label: 'Document', width: '1.6fr', sort: 'title' },
    { key: 'type', label: 'Type', width: '1.2fr', sort: 'type' },
    { key: 'deleted_by', label: 'Deleted by', width: '1.2fr' },
    { key: 'deleted_at', label: 'Deleted', width: '1.3fr', sort: 'deleted' },
    { key: 'was', label: 'Was', width: '1fr' },
]

const retention = computed(() => (props.days > 0 ? `Documents are kept for ${props.days} days, then removed for good` : 'Documents stay here until you delete them'))

/* restore: bina sawal ke (nuqsan nahi), purge: tasdeeq ke saath */
function restore(ids, clear) {
    router.post('/settings/recycle-bin/restore', { ids }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { clear?.(); list.refresh() },
    })
}

const purgeOpen = ref(false)
const purge = ref({ ids: [], clear: null })
const askPurge = (ids, clear) => { purge.value = { ids, clear }; purgeOpen.value = true }
function runPurge() {
    router.post('/settings/recycle-bin/purge', { ids: purge.value.ids }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { purge.value.clear?.(); list.refresh() },
        onFinish: () => (purgeOpen.value = false),
    })
}
</script>

<template>
    <ListPage title="Recycle Bin" :description="retention">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="16" />
            </button>
        </template>

        <ListView :columns="columns" :rows="list.rows" :total="list.total" :loading="list.loading" :error="list.error"
            label="Recycle Bin" :filtered="list.isFiltered" :has-more="list.hasMore" :loading-more="list.loadingMore"
            v-model:page-size="list.pageSize" v-model:sort="list.sort" :reset-key="list.resetKey" :clickable="false"
            @load-more="list.loadMore()" @retry="list.retry()" @clear-filters="list.clearFilters()">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search by name"
                    aria-label="Search deleted documents" class="w-56" />
                <div class="w-48"><Select v-model="list.filters.type" :options="types" placeholder="Document type"
                        clearable /></div>
            </template>
            <template #filter-actions>
                <button v-if="list.isFiltered" type="button" class="btn" @click="list.clearFilters()">Clear
                    filters</button>
            </template>

            <template #cell-title="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-deleted_at="{ value }"><span class="text-muted">{{ dateTime(value) }}</span></template>
            <template #cell-was="{ value }">
                <StatusBadge :tone="value === 'Cancelled' ? 'danger' : 'warning'">{{ value }}</StatusBadge>
            </template>

            <template #row-end="{ row }">
                <RowAction v-if="row.can_restore" :label="`Restore ${row.title} as Draft`" icon="restore"
                    @click="restore([row.id])" />
                <RowAction v-if="row.can_purge" :label="`Delete ${row.title} forever`" danger
                    @click="askPurge([row.id])" />
            </template>

            <template #bulk="{ ids, clear }">
                <button v-if="can('settings.recycle_bin.restore')" type="button" class="btn"
                    @click="restore(ids, clear)">Restore as Draft</button>
                <button v-if="can('settings.recycle_bin.purge')" type="button" class="btn btn-danger"
                    @click="askPurge(ids, clear)">Delete forever</button>
            </template>
        </ListView>

        <ConfirmDialog v-model="purgeOpen" title="Delete forever?" confirm-label="Delete forever" danger
            @confirm="runPurge">
            <p><strong>{{ purge.ids.length }}</strong> document(s) and their attachments will be permanently deleted.
                This
                cannot be undone.</p>
        </ConfirmDialog>
    </ListPage>
</template>