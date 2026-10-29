<script setup>
import Icon from './Icon.vue'

defineProps({ label: { type: String, required: true }, icon: { type: String, default: 'trash' }, danger: Boolean })
</script>

<template>
    <button type="button" :aria-label="label" :title="label"
        class="flex size-8 items-center justify-center rounded-md text-muted opacity-0 outline-none transition-opacity hover:bg-bg focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-accent group-focus-within:opacity-100 group-hover:opacity-100"
        :class="danger ? 'hover:text-red-700' : 'hover:text-ink'" @click.stop>
        <Icon :name="icon" :size="16" />
    </button>
</template>