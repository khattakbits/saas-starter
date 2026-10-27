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

        <section v-if="$slots.filters" class="flex-none rounded-lg border border-line bg-surface p-3">
            <slot name="filters" />
        </section>

        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-line bg-surface">
            <div class="noscroll min-h-0 flex-1 overflow-auto">
                <slot />
            </div>
            <div v-if="$slots.footer"
                class="flex h-8 flex-none items-center justify-between border-t border-line px-3 text-[11px] text-muted">
                <slot name="footer" />
            </div>
        </section>
    </div>
</template>