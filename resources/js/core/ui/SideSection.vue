<script setup>
import Icon from './Icon.vue'

defineProps({ title: String, icon: String })
</script>

<template>
    <section class="border-b border-line/70 py-3 first:pt-0">
        <h3 class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold text-muted">
            <Icon v-if="icon" :name="icon" :size="13" />{{ title }}
        </h3>
        <slot />
    </section>
</template>