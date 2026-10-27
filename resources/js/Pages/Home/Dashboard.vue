<script setup>
import AppShell from '@core/layouts/AppShell.vue'
import Page from '@core/layouts/Page.vue'
import EmptyState from '@core/ui/EmptyState.vue'

defineOptions({ layout: AppShell })
</script>

<template>
    <Page title="Dashboard" description="Overview of your workspace">
        <div class="rounded-lg border border-line bg-surface">
            <EmptyState variant="widgets" />
        </div>
    </Page>
</template>