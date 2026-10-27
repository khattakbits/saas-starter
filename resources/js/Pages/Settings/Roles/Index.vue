<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import ListView from '@core/ui/ListView.vue'
import Input from '@core/ui/Input.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import Icon from '@core/ui/Icon.vue'
import { useCan } from '@core/composables/useCan'
import { useServerList } from '@core/composables/useServerList'

defineOptions({ layout: AppShell })
const can = useCan()

const list = useServerList({ endpoint: '/settings/roles/data', sort: { key: 'name', dir: 'asc' } })

const columns = [
    { key: 'name', label: 'Role', width: '2fr', sort: 'name' },
    { key: 'users', label: 'Users', width: '1fr', align: 'right' },
    { key: 'permissions', label: 'Permissions', width: '1fr', align: 'right' },
    { key: 'type', label: 'Type', width: '1fr' },
]

const open = row => {
    if (row.system) return   // super-admin ka form nahi hota
    router.visit(`/settings/roles/${row.id}`)
}

/* ---------- delete ---------- */
const canDelete = computed(() => can('settings.roles.delete'))
// super-admin protected; assigned roles ko pehle users se hatana parta hai
const isDeletable = row => !row.system && row.users === 0

const confirmOpen = ref(false)
const pending = ref([])
const deletable = computed(() => list.rows.filter(r => pending.value.includes(r.id) && isDeletable(r)))
const skipped = computed(() => pending.value.length - deletable.value.length)

function askDelete(ids) {
    pending.value = ids
    confirmOpen.value = true
}

function runDelete() {
    router.post('/settings/roles/bulk-delete', { ids: deletable.value.map(r => r.id) }, {
        preserveScroll: true,
        onSuccess: () => list.refresh(),
        onFinish: () => (confirmOpen.value = false),
    })
}
</script>

<template>
    <ListPage title="Roles" description="Groups of permissions you can assign to users">
        <template #actions>
            <button type="button" class="btn btn-icon" aria-label="Refresh" @click="list.refresh()">
                <Icon name="refresh" :size="13" />
            </button>
            <Link v-if="can('settings.roles.create')" href="/settings/roles/create" class="btn btn-primary">
                <Icon name="plus" :size="13" class="mr-1" />Add Role
            </Link>
        </template>

        <ListView :columns="columns" :rows="list.rows" :total="list.total" :loading="list.loading" :error="list.error"
            :filtered="list.isFiltered" :has-more="list.hasMore" :loading-more="list.loadingMore"
            v-model:page-size="list.pageSize" v-model:sort="list.sort" :reset-key="list.resetKey"
            :selectable="canDelete"
            :clickable="can('settings.roles.update')" @load-more="list.loadMore()" @retry="list.retry()"
            @clear-filters="list.clearFilters()" @row-click="open">
            <template #filters>
                <Input v-model="list.search" type="search" placeholder="Search roles" aria-label="Search roles"
                    class="w-56" />
            </template>
            <template #cell-name="{ value }"><span class="font-medium">{{ value }}</span></template>
            <template #cell-type="{ row }">
                <StatusBadge :tone="row.system ? 'warning' : 'neutral'">{{ row.system ? 'Full access' : 'Custom' }}
                </StatusBadge>
            </template>

            <template #row-end="{ row }">
                <button v-if="canDelete && isDeletable(row)" type="button"
                    class="btn btn-icon text-muted hover:text-red-600" :aria-label="`Delete role ${row.name}`"
                    :title="`Delete role ${row.name}`" @click.stop="askDelete([row.id])">
                    <Icon name="trash" :size="12" />
                </button>
            </template>

            <template #bulk="{ ids }">
                <button v-if="canDelete" type="button" class="btn btn-danger" @click="askDelete(ids)">Delete</button>
            </template>

            <template #empty-actions>
                <Link v-if="can('settings.roles.create')" href="/settings/roles/create" class="btn btn-primary">Add Role
                </Link>
            </template>
        </ListView>

        <ConfirmDialog v-model="confirmOpen" title="Delete role(s)?" confirm-label="Delete" danger
            :disabled="!deletable.length" @confirm="runDelete">
            <p v-if="deletable.length"><strong>{{ deletable.length }}</strong> role(s) will be deleted. This cannot be
                undone.</p>
            <p v-else>None of the selected roles can be deleted.</p>
            <p v-if="skipped" class="mt-2 text-muted">{{ skipped }} role(s) will be skipped. Super admin and roles still
                assigned to users cannot be deleted.</p>
        </ConfirmDialog>
    </ListPage>
</template>