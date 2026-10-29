<script setup>
import { router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import ListView from '@core/ui/ListView.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import DatePicker from '@core/ui/DatePicker.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import Icon from '@core/ui/Icon.vue'
import { useServerList } from '@core/composables/useServerList'
import { useFormat } from '@core/composables/useFormat'

defineOptions({ layout: AppShell })
const props = defineProps({ events: Array, filter: Object })
const { dateTime } = useFormat()

const list = useServerList({
    endpoint: '/settings/audit-log/data',
    sort: { key: 'when', dir: 'desc' },
    filters: { event: '', user: props.filter.user, from: '', to: '' },
})

const columns = [
    { key: 'when', label: 'When', width: '1.3fr', sort: 'when' },
    { key: 'user', label: 'User', width: '1.2fr' },
    { key: 'event', label: 'Event', width: '1.3fr', sort: 'event' },
    { key: 'subject', label: 'Subject', width: '2fr' },
    { key: 'ip', label: 'IP address', width: '1fr' },
]

const labelOf = v => props.events.find(e => e.value === v)?.label ?? v
const toneOf = v => (v.endsWith('failed') || v.endsWith('deleted') ? 'danger' : v.startsWith('auth') ? 'neutral' : v.endsWith('created') ? 'success' : 'info')
</script>

<template>
    <ListPage title="Audit Log" description="Who did what, and when. Click an entry to see the details">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="16" />
            </button>
        </template>

        <ListView :columns="columns" :rows="list.rows" :loading="list.loading" :error="list.error" label="Audit log"
            :filtered="list.isFiltered" :has-more="list.hasMore" :loading-more="list.loadingMore"
            v-model:page-size="list.pageSize" v-model:sort="list.sort" :reset-key="list.resetKey" :selectable="false"
            @row-click="row => router.visit(`/settings/audit-log/${row.id}`)" @load-more="list.loadMore()"
            @retry="list.retry()" @clear-filters="list.clearFilters()">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search subject" aria-label="Search subject"
                    class="w-48" />
                <div class="w-52">
                    <Select v-model="list.filters.user" endpoint="/settings/audit-log/users" :display="filter.userLabel"
                        placeholder="User" clearable />
                </div>
                <div class="w-48"><Select v-model="list.filters.event" :options="events" placeholder="Event"
                        clearable /></div>
                <div class="w-36">
                    <DatePicker v-model="list.filters.from" placeholder="From date" />
                </div>
                <div class="w-36">
                    <DatePicker v-model="list.filters.to" placeholder="To date" />
                </div>
            </template>
            <template #filter-actions>
                <button v-if="list.isFiltered" type="button" class="btn" @click="list.clearFilters()">Clear
                    filters</button>
            </template>

            <template #cell-when="{ value }">{{ dateTime(value, true) }}</template>
            <template #cell-event="{ value }">
                <StatusBadge :tone="toneOf(value)">{{ labelOf(value) }}</StatusBadge>
            </template>
        </ListView>
    </ListPage>
</template>