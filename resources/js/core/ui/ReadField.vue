<script setup>
defineProps({ label: String, value: [String, Number] })
</script>

<template>
    <div class="flex flex-col gap-1">
        <span class="text-[11px] font-semibold text-ink/80">{{ label }}</span>
        <div class="min-h-7 rounded-md bg-bg px-2 py-1.5">
            <slot>{{ value }}</slot>
        </div>
    </div>
</template>