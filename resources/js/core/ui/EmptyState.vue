<script setup>
import { computed } from 'vue'

const props = defineProps({
    variant: { type: String, default: 'records' },
    title: String,
    description: String,
    compact: Boolean,
})

const presets = {
    records: { title: 'No records yet', description: 'Create the first record and it will show up here.', icon: 'M4 6h16M4 12h16M4 18h10' },
    search: { title: 'No matching records', description: 'Try a different search or clear the filters.', icon: 'M11 4a7 7 0 100 14 7 7 0 000-14zM21 21l-4.3-4.3' },
    report: { title: 'Report not run yet', description: 'Choose the filters above and press Run.', icon: 'M5 20V10M12 20V4M19 20v-7' },
    error: { title: 'Could not load data', description: 'Something went wrong. Please try again.', icon: 'M12 8v5M12 17h.01M10.3 3.9L2.4 18a2 2 0 001.7 3h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z' },
    forbidden: { title: 'Access denied', description: 'Your role does not include this page.', icon: 'M7 11V8a5 5 0 0110 0v3M5 11h14v9H5z' },
    notfound: { title: 'Page not found', description: 'This address does not exist.', icon: 'M9.5 9.5a2.5 2.5 0 114 2c-.8.6-1.5 1-1.5 2M12 17h.01M12 3a9 9 0 100 18 9 9 0 000-18z' },
    widgets: { title: 'Nothing to show yet', description: 'Widgets will appear here once there is data.', icon: 'M4 4h7v7H4zM13 4h7v4h-7zM13 11h7v9h-7zM4 14h7v6H4z' },
}
const preset = computed(() => presets[props.variant] ?? presets.records)
</script>

<template>
    <div role="status" class="flex flex-col items-center justify-center text-center"
        :class="compact ? 'gap-1 py-6' : 'gap-1.5 py-14'">
        <span class="mb-1 flex size-9 items-center justify-center rounded-full bg-accent-soft text-accent-dark">
            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path :d="preset.icon" />
            </svg>
        </span>
        <p class="text-[12px] font-semibold">{{ title ?? preset.title }}</p>
        <p v-if="description ?? preset.description" class="max-w-xs text-muted">{{ description ?? preset.description }}
        </p>
        <div v-if="$slots.default" class="mt-2 flex gap-2">
            <slot />
        </div>
    </div>
</template>