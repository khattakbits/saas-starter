<script setup>
import AppShell from '@core/layouts/AppShell.vue'
import Page from '@core/layouts/Page.vue'
import EmptyState from '@core/ui/EmptyState.vue'

defineOptions({ layout: AppShell })

const variants = ['records', 'search', 'report', 'error', 'forbidden', 'notfound', 'widgets']
</script>

<template>
    <Page title="Empty states" description="Every state the starter kit ships with">
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-3">
            <div v-for="v in variants" :key="v" class="rounded-lg border border-line bg-surface">
                <div
                    class="border-b border-line px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted">
                    {{ v }}</div>
                <EmptyState :variant="v" compact />
            </div>
        </div>
        <p class="mt-4 text-muted">
            Full-page error screens:
            <a href="/examples/forbidden" class="text-accent-dark underline">403 page</a> &middot;
            <a href="/examples/does-not-exist" class="text-accent-dark underline">404 page</a>
        </p>
    </Page>
</template>