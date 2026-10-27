<script setup>
defineProps({ tone: { type: String, default: 'neutral' }, label: String })

const tones = {
    neutral: 'bg-gray-100 text-gray-700',
    info: 'bg-blue-100 text-blue-800',
    success: 'bg-green-100 text-green-800',
    warning: 'bg-amber-100 text-amber-800',
    danger: 'bg-red-100 text-red-700',
}
</script>

<template>
    <span class="inline-flex items-center gap-1 rounded px-1.5 py-px text-[11px] font-medium"
        :class="tones[tone] ?? tones.neutral">
        <span class="size-1 rounded-full bg-current" aria-hidden="true"></span>
        <slot>{{ label }}</slot>
    </span>
</template>