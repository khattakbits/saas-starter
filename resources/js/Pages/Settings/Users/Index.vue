<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import ListView from '@core/ui/ListView.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import Icon from '@core/ui/Icon.vue'
import { useCan } from '@core/composables/useCan'
import { useServerList } from '@core/composables/useServerList'
import { useFormat } from '@core/composables/useFormat'

const { dateTime } = useFormat()

defineOptions({ layout: AppShell })
const props = defineProps({ roles: Array })
const can = useCan()

const list = useServerList({
    endpoint: '/settings/users/data',
    sort: { key: 'name', dir: 'asc' },
    filters: { status: '', role: '' },
})

const columns = [
    { key: 'name', label: 'Name', width: '1.6fr', sort: 'name' },
    { key: 'email', label: 'Email', width: '2fr', sort: 'email' },
    { key: 'roles', label: 'Roles', width: '1.6fr' },
    { key: 'status', label: 'Status', width: '1fr', sort: 'status' },
    { key: 'last_login', label: 'Last login', width: '1.2fr' },
]
const statusOptions = [{ value: 'active', label: 'Active' }, { value: 'disabled', label: 'Disabled' }]
const roleOptions = props.roles.map(r => ({ value: r, label: r }))

/* bulk enable / disable */
const confirmOpen = ref(false)
const bulk = ref({ ids: [], active: false, clear: null })

function askBulk(ids, active, clear) {
    bulk.value = { ids, active, clear }
    confirmOpen.value = true
}

function runBulk() {
    router.post('/settings/users/bulk-status', { ids: bulk.value.ids, active: bulk.value.active }, {
        preserveScroll: true,
        onSuccess: () => { bulk.value.clear?.(); list.refresh() },
        onFinish: () => (confirmOpen.value = false),
    })
}
</script>

<template>
    <ListPage title="Users" description="People who can sign in to this workspace">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="13" />
            </button>
            <Link v-if="can('settings.users.create')" href="/settings/users/create" class="btn btn-primary">
                <Icon name="plus" :size="13" class="mr-1" />Add User
            </Link>
        </template>

        <ListView :columns="columns" :rows="list.rows" :total="list.total" :loading="list.loading" :error="list.error"
            :filtered="list.isFiltered" :has-more="list.hasMore" :loading-more="list.loadingMore"
            v-model:page-size="list.pageSize" v-model:sort="list.sort" :reset-key="list.resetKey"
            :clickable="can('settings.users.update')" :selectable="can('settings.users.update')"
            @load-more="list.loadMore()" @retry="list.retry()" @clear-filters="list.clearFilters()"
            @row-click="row => router.visit(`/settings/users/${row.id}`)">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search name or email" aria-label="Search users"
                    class="w-56" />
                <div class="w-36"><Select v-model="list.filters.status" :options="statusOptions" placeholder="Status"
                        clearable /></div>
                <div class="w-40"><Select v-model="list.filters.role" :options="roleOptions" placeholder="Role"
                        clearable /></div>
            </template>
            <template #filter-actions>
                <button v-if="list.isFiltered" type="button" class="btn" @click="list.clearFilters()">Clear
                    filters</button>
            </template>

            <template #cell-name="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-last_login="{ value }"><span class="text-muted">{{ dateTime(value) || 'Never'
            }}</span></template>
            <template #cell-roles="{ row }">
                <span v-if="row.roles.length" class="flex gap-1 overflow-hidden">
                    <StatusBadge v-for="r in row.roles" :key="r" tone="info">{{ r }}</StatusBadge>
                </span>
                <span v-else class="text-muted">None</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="row.is_active ? 'success' : 'neutral'">{{ row.status }}</StatusBadge>
            </template>

            <template #bulk="{ ids, clear }">
                <button type="button" class="btn" @click="askBulk(ids, true, clear)">Enable</button>
                <button type="button" class="btn btn-danger" @click="askBulk(ids, false, clear)">Disable</button>
            </template>
            <template #empty-actions>
                <Link v-if="can('settings.users.create')" href="/settings/users/create" class="btn btn-primary">Add User
                </Link>
            </template>
        </ListView>

        <ConfirmDialog v-model="confirmOpen" :title="bulk.active ? 'Enable users?' : 'Disable users?'"
            :confirm-label="bulk.active ? 'Enable' : 'Disable'" :danger="!bulk.active" @confirm="runBulk">
            <p v-if="bulk.active"><strong>{{ bulk.ids.length }}</strong> user(s) will be able to sign in again.</p>
            <p v-else><strong>{{ bulk.ids.length }}</strong> user(s) will be signed out and cannot sign in until enabled
                again.
            </p>
        </ConfirmDialog>
    </ListPage>
</template>