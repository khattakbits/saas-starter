<script setup>
import PageHeader from './PageHeader.vue'

defineProps({ title: String, description: String, back: String, backLabel: String })
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <PageHeader :title="title" :description="description" :back="back" :back-label="backLabel">
            <template #badge>
                <slot name="badge" />
            </template>
            <template #actions>
                <slot name="actions" />
            </template>
        </PageHeader>
        <slot />
    </div>
</template>