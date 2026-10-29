<script setup>
import { ref, useId, watch } from 'vue'
import Icon from '@core/ui/Icon.vue'

const props = defineProps({
    title: String,
    icon: String,                              // Icon.vue ka naam
    when: { type: Boolean, default: true },    // false ho to section render hi nahi hota
    collapsible: Boolean,
    collapsed: Boolean,                        // collapsible ho to shuru mein band
    hasError: Boolean,                         // andar koi error ho to khud khul jaye
})

const bodyId = useId()
const open = ref(!props.collapsible || !props.collapsed)

watch(() => props.hasError, v => { if (v) open.value = true })
</script>

<template>
    <section v-if="when">
        <h2 v-if="collapsible && title" class="m-0">
            <button type="button"
                class="flex w-full items-center justify-between gap-3 bg-bg/70 px-4 py-3 text-left outline-none hover:bg-bg focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent"
                :aria-expanded="open" :aria-controls="bodyId" @click="open = !open">
                <span class="flex items-center gap-2.5">
                    <span
                        class="flex size-7 flex-none items-center justify-center rounded-md bg-accent-soft text-accent-dark">
                        <Icon :name="icon || 'layers'" :size="16" />
                    </span>
                    <span class="text-[14px] font-bold">{{ title }}</span>
                    <span v-if="hasError" class="size-2 rounded-full bg-red-600" role="img"
                        aria-label="Contains errors"></span>
                </span>
                <span class="flex items-center gap-1.5 text-[12px] font-medium text-muted">
                    {{ open ? 'Hide' : 'Show' }}
                    <span
                        class="flex size-6 items-center justify-center rounded border border-line bg-surface transition-transform"
                        :class="open ? 'rotate-180' : ''">
                        <Icon name="chevron-down" :size="14" />
                    </span>
                </span>
            </button>
        </h2>
        <div v-else-if="title" class="flex items-center gap-2.5 px-4 pb-1 pt-3.5">
            <span v-if="icon"
                class="flex size-7 flex-none items-center justify-center rounded-md bg-accent-soft text-accent-dark">
                <Icon :name="icon" :size="16" />
            </span>
            <h2 class="text-[14px] font-bold">{{ title }}</h2>
        </div>

        <div v-show="open" :id="bodyId" class="px-4 pb-4" :class="title ? 'pt-3' : 'pt-4'">
            <slot />
        </div>
    </section>
</template>