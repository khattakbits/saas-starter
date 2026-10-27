<script setup>
import { ref, useId, watch } from 'vue'
import Icon from '@core/ui/Icon.vue'

const props = defineProps({
    title: String,
    when: { type: Boolean, default: true },   // false ho to section render hi nahi hota
    collapsible: Boolean,
    collapsed: Boolean,                       // collapsible ho to shuru mein band
    hasError: Boolean,                        // andar koi error ho to khud khul jaye
})

const bodyId = useId()
const open = ref(!props.collapsible || !props.collapsed)

watch(() => props.hasError, v => { if (v) open.value = true })
</script>

<template>
    <section v-if="when">
        <button v-if="collapsible && title" type="button"
            class="flex w-full items-center justify-between gap-2 bg-bg/70 px-4 py-2.5 text-left outline-none hover:bg-bg focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-accent"
            :aria-expanded="open" :aria-controls="bodyId" @click="open = !open">
            <span class="flex items-center gap-1.5 text-[12px] font-semibold">
                {{ title }}
                <span v-if="hasError" class="size-1.5 rounded-full bg-red-600" role="img"
                    aria-label="Contains errors"></span>
            </span>
            <span class="flex items-center gap-1 text-[11px] font-medium text-muted">
                {{ open ? 'Hide' : 'Show' }}
                <span
                    class="flex size-5 items-center justify-center rounded border border-line bg-surface transition-transform"
                    :class="open ? 'rotate-180' : ''">
                    <Icon name="chevron-down" :size="12" />
                </span>
            </span>
        </button>
        <div v-else-if="title" class="px-4 pb-1 pt-3 text-[12px] font-semibold">{{ title }}</div>

        <div v-show="open" :id="bodyId" class="px-4 pb-4"
            :class="collapsible && title ? 'pt-3' : title ? 'pt-2' : 'pt-4'">
            <slot />
        </div>
    </section>
</template>