<script setup>
defineProps({ cols: { type: Number, default: 2 } })
</script>

<template>
    <div class="grid gap-x-8 gap-y-3"
        :class="{ 'grid-cols-1': cols === 1, 'grid-cols-2': cols === 2, 'grid-cols-3': cols === 3 }">
        <slot />
    </div>
</template>