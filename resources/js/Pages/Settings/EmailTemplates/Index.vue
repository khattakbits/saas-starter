<script setup>
import { Link } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import ListPage from '@core/layouts/ListPage.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import EmptyState from '@core/ui/EmptyState.vue'

defineOptions({ layout: AppShell })
defineProps({ templates: Array })

const grid = { gridTemplateColumns: '1.6fr 1.3fr 1fr 0.8fr' }
</script>

<template>
    <ListPage title="Email Templates" description="Text of the emails this workspace sends">
        <div class="min-h-0 flex-1 overflow-hidden rounded-lg border border-line bg-surface">
            <div class="grid h-8 flex-none items-center border-b border-line bg-bg px-3 text-[11px] font-semibold text-muted"
                :style="grid">
                <span>Name</span>
                <span>Key</span>
                <span>State</span>
                <span>Active</span>
            </div>

            <EmptyState v-if="!templates?.length" variant="records" />

            <div v-else class="noscroll min-h-0 overflow-y-auto">
                <Link v-for="t in templates" :key="t.key" :href="`/settings/email-templates/${t.key}`"
                    class="grid h-9 items-center border-b border-line/60 px-3 text-[13px] outline-none last:border-b-0 hover:bg-accent-soft/60 focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-accent"
                    :style="grid">
                    <span class="truncate font-medium">{{ t.name }}</span>
                    <span class="truncate font-mono text-[12px] text-muted">{{ t.key }}</span>
                    <span>
                        <StatusBadge :tone="t.customized ? 'info' : 'neutral'">{{ t.customized ? 'Customized' :
                            'Default' }}</StatusBadge>
                    </span>
                    <span>
                        <StatusBadge :tone="t.is_active ? 'success' : 'neutral'">{{ t.is_active ? 'Active' : 'Off'
                            }}</StatusBadge>
                    </span>
                </Link>
            </div>
        </div>
    </ListPage>
</template>
