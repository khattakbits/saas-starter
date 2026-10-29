<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import ListView from '@core/ui/ListView.vue'
import Input from '@core/ui/Input.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import RowAction from '@core/ui/RowAction.vue'
import Icon from '@core/ui/Icon.vue'
import { useCan } from '@core/composables/useCan'
import { useServerList } from '@core/composables/useServerList'
import { useRowDelete } from '@core/composables/useRowDelete'

defineOptions({ layout: AppShell })
const can = useCan()

const list = useServerList({ endpoint: '/settings/roles/data', sort: { key: 'name', dir: 'asc' } })
const del = reactive(useRowDelete({ url: r => `/settings/roles/${r.id}`, onDone: () => list.refresh() }))

const columns = [
    { key: 'name', label: 'Role', width: '2fr', sort: 'name' },
    { key: 'users', label: 'Users', width: '1fr', align: 'right' },
    { key: 'permissions', label: 'Permissions', width: '1fr', align: 'right' },
    { key: 'type', label: 'Type', width: '1fr' },
]

const open = row => {
    if (row.system || !row.editable) return   // super-admin ya apne se bari role
    router.visit(`/settings/roles/${row.id}`)
}
</script>

<template>
    <ListPage title="Roles" description="Groups of permissions you can assign to users">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="16" />
            </button>
            <Link v-if="can('settings.roles.create')" href="/settings/roles/create" class="btn btn-primary">
                <Icon name="plus" :size="16" />Add Role
            </Link>
        </template>

        <ListView :columns="columns" :rows="list.rows" :total="list.total" :loading="list.loading" :error="list.error"
            label="Roles" :filtered="list.isFiltered" :has-more="list.hasMore" :loading-more="list.loadingMore"
            v-model:page-size="list.pageSize" v-model:sort="list.sort" :reset-key="list.resetKey" :selectable="false"
            :clickable="can('settings.roles.update')" @load-more="list.loadMore()" @retry="list.retry()"
            @clear-filters="list.clearFilters()" @row-click="open">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search roles" aria-label="Search roles"
                    class="w-56" />
            </template>
            <template #cell-name="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-type="{ row }">
                <StatusBadge :tone="row.system ? 'warning' : row.editable ? 'neutral' : 'danger'">
                    {{ row.system ? 'Full access' : row.editable ? 'Custom' : 'Locked' }}
                </StatusBadge>
            </template>
            <template #row-end="{ row }">
                <RowAction v-if="row.deletable && can('settings.roles.delete')" :label="`Delete role ${row.name}`"
                    danger @click="del.ask(row)" />
            </template>
            <template #empty-actions>
                <Link v-if="can('settings.roles.create')" href="/settings/roles/create" class="btn btn-primary">Add Role
                </Link>
            </template>
        </ListView>

        <ConfirmDialog v-model="del.open" title="Delete role?" confirm-label="Delete" danger :disabled="del.busy"
            @confirm="del.confirm">
            <p>Role <strong>{{ del.target?.name }}</strong> will be deleted. This cannot be undone.</p>
        </ConfirmDialog>
    </ListPage>
</template>