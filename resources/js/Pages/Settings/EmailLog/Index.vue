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
const props = defineProps({ templates: Array })
const { dateTime } = useFormat()

const list = useServerList({
    endpoint: '/settings/email-log/data',
    sort: { key: 'when', dir: 'desc' },
    filters: { status: '', template: '', from: '', to: '' },
})

const columns = [
    { key: 'when', label: 'When', width: '1.4fr', sort: 'when' },
    { key: 'to', label: 'To', width: '1.8fr', sort: 'to' },
    { key: 'subject', label: 'Subject', width: '2.2fr' },
    { key: 'template', label: 'Template', width: '1fr' },
    { key: 'status', label: 'Status', width: '0.9fr', sort: 'status' },
    { key: 'account', label: 'Account', width: '1.2fr' },
    { key: 'attempts', label: 'Attempts', width: '0.7fr', align: 'right' },
]

const statusOptions = [
    { value: 'queued', label: 'Queued' },
    { value: 'sent', label: 'Sent' },
    { value: 'failed', label: 'Failed' },
]
const templateOptions = (props.templates ?? []).map(t => ({ value: t, label: t }))
const tones = { sent: 'success', failed: 'danger', queued: 'warning' }
</script>

<template>
    <ListPage title="Email Log" description="Every email this workspace has tried to send">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="13" />
            </button>
        </template>

        <ListView :columns="columns" :rows="list.rows" :total="list.total" :loading="list.loading" :error="list.error"
            label="Email log" :filtered="list.isFiltered" :has-more="list.hasMore" :loading-more="list.loadingMore"
            v-model:page-size="list.pageSize" v-model:sort="list.sort" :reset-key="list.resetKey" :selectable="false"
            @load-more="list.loadMore()" @retry="list.retry()" @clear-filters="list.clearFilters()"
            @row-click="row => router.visit(`/settings/email-log/${row.id}`)">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search recipient" aria-label="Search recipient"
                    class="w-56" />
                <div class="w-36">
                    <Select v-model="list.filters.status" :options="statusOptions" placeholder="Status" clearable />
                </div>
                <div class="w-40">
                    <Select v-model="list.filters.template" :options="templateOptions" placeholder="Template" clearable />
                </div>
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

            <template #cell-when="{ value }"><span class="text-muted">{{ dateTime(value) }}</span></template>
            <template #cell-to="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-template="{ value }"><span class="text-muted">{{ value || '-' }}</span></template>
            <template #cell-status="{ value }">
                <StatusBadge :tone="tones[value] ?? 'neutral'">{{ value }}</StatusBadge>
            </template>
            <template #cell-account="{ value }"><span class="text-muted">{{ value || '-' }}</span></template>
        </ListView>
    </ListPage>
</template>
