<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
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
import { useRowDelete } from '@core/composables/useRowDelete'
import { useFormat } from '@core/composables/useFormat'

defineOptions({ layout: AppShell })
const can = useCan()
const { dateTime } = useFormat()

const list = useServerList({
    endpoint: '/settings/email-accounts/data',
    sort: { key: 'name', dir: 'asc' },
    filters: { status: '' },
})
const del = reactive(useRowDelete({ url: r => `/settings/email-accounts/${r.id}`, onDone: () => list.refresh() }))

const columns = [
    { key: 'name', label: 'Name', width: '1.5fr', sort: 'name' },
    { key: 'from', label: 'From', width: '1.9fr' },
    { key: 'host', label: 'Host', width: '1.5fr', sort: 'host' },
    { key: 'encryption', label: 'Encryption', width: '0.9fr' },
    { key: 'is_default', label: 'Default', width: '0.8fr' },
    { key: 'status', label: 'Status', width: '0.9fr', sort: 'status' },
    { key: 'last_test', label: 'Last test', width: '1.3fr' },
]
const statusOptions = [{ value: 'active', label: 'Active' }, { value: 'disabled', label: 'Disabled' }]

const open = row => can('settings.email_accounts.update') && router.visit(`/settings/email-accounts/${row.id}`)
</script>

<template>
    <ListPage title="Email Accounts" description="SMTP accounts used to send email from this workspace">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="13" />
            </button>
            <Link v-if="can('settings.email_accounts.create')" href="/settings/email-accounts/create"
                class="btn btn-primary">
                <Icon name="plus" :size="13" />Add Account
            </Link>
        </template>

        <ListView :columns="columns" :rows="list.rows" :total="list.total" :loading="list.loading" :error="list.error"
            label="Email accounts" :filtered="list.isFiltered" :has-more="list.hasMore"
            :loading-more="list.loadingMore" v-model:page-size="list.pageSize" v-model:sort="list.sort"
            :reset-key="list.resetKey" :selectable="false" :clickable="can('settings.email_accounts.update')"
            @load-more="list.loadMore()" @retry="list.retry()" @clear-filters="list.clearFilters()"
            @row-click="open">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search name" aria-label="Search email accounts"
                    class="w-56" />
                <div class="w-36">
                    <Select v-model="list.filters.status" :options="statusOptions" placeholder="Status" clearable />
                </div>
            </template>
            <template #filter-actions>
                <button v-if="list.isFiltered" type="button" class="btn" @click="list.clearFilters()">Clear
                    filters</button>
            </template>

            <template #cell-name="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-host="{ row }"><span class="text-muted">{{ row.host }}:{{ row.port }}</span></template>
            <template #cell-encryption="{ value }"><span class="text-muted">{{ value }}</span></template>
            <template #cell-is_default="{ row }">
                <StatusBadge v-if="row.is_default" tone="success">Default</StatusBadge>
                <span v-else class="text-muted">-</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="row.is_active ? 'success' : 'neutral'">{{ row.status }}</StatusBadge>
            </template>
            <template #cell-last_test="{ row }">
                <span v-if="row.last_test === 'Never'" class="text-muted">Never</span>
                <span v-else class="flex items-center gap-1.5">
                    <StatusBadge :tone="row.last_test_ok ? 'success' : 'danger'">{{ row.last_test }}</StatusBadge>
                    <span class="text-[11px] text-muted">{{ dateTime(row.last_tested_at) }}</span>
                </span>
            </template>

            <template #row-end="{ row }">
                <RowAction v-if="can('settings.email_accounts.delete')" :label="`Delete account ${row.name}`" danger
                    @click="del.ask(row)" />
            </template>
            <template #empty-actions>
                <Link v-if="can('settings.email_accounts.create')" href="/settings/email-accounts/create"
                    class="btn btn-primary">Add Account</Link>
            </template>
        </ListView>

        <ConfirmDialog v-model="del.open" title="Delete email account?" confirm-label="Delete" danger
            :disabled="del.busy" @confirm="del.confirm">
            <p><strong>{{ del.target?.name }}</strong> will be deleted. Emails already logged stay in the Email Log.</p>
        </ConfirmDialog>
    </ListPage>
</template>
