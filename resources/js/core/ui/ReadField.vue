<script setup>
defineProps({
    label: String,
    value: [String, Number],
    size: { type: String, default: 'full' },
    span: { type: Number, default: 1 },
})

const spans = { 1: '', 2: 'col-span-2', 3: 'col-span-3', 4: 'col-span-4' }
const widths = { xs: 'max-w-32', sm: 'max-w-48', md: 'max-w-64', lg: 'max-w-96', full: '' }
</script>

<template>
    <div class="flex flex-col gap-1.5" :class="spans[span]">
        <span class="text-[12px] font-semibold text-ink/80">{{ label }}</span>
        <div class="min-h-8 rounded-md bg-bg px-2.5 py-1.5 text-[13px]" :class="widths[size]">
            <slot>{{ value }}</slot>
        </div>
    </div>
</template>