<script setup>
defineProps({ cols: { type: Number, default: 2 } })
</script>

<template>
    <div class="grid gap-x-6 gap-y-4"
        :class="{ 'grid-cols-1': cols === 1, 'grid-cols-2': cols === 2, 'grid-cols-3': cols === 3, 'grid-cols-4': cols === 4 }">
        <slot />
    </div>
</template>