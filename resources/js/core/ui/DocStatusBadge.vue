<script setup>
import StatusBadge from './StatusBadge.vue'

defineProps({ status: { type: Number, required: true } })
const map = { 0: ['warning', 'Draft'], 1: ['info', 'Submitted'], 2: ['danger', 'Cancelled'] }
</script>

<template>
    <StatusBadge :tone="map[status]?.[0] ?? 'neutral'">{{ map[status]?.[1] ?? status }}</StatusBadge>
</template>