<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from './Icon.vue'

const page = usePage()
const items = ref([])
const timers = new Set()
let uid = 0

const dismiss = id => (items.value = items.value.filter(i => i.id !== id))

function push(type, text) {
    const id = ++uid
    items.value.push({ id, type, text })
    timers.add(setTimeout(() => dismiss(id), type === 'error' ? 6000 : 3500))
}

watch(() => page.props.flash, f => {
    if (f?.success) push('success', f.success)
    if (f?.error) push('error', f.error)
}, { immediate: true })

onBeforeUnmount(() => timers.forEach(clearTimeout))
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-14 z-60 flex w-80 flex-col gap-2" aria-live="polite">
        <div v-for="t in items" :key="t.id" role="status"
            class="pointer-events-auto flex items-start gap-2 rounded-lg border px-3 py-2 shadow-lg"
            :class="t.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-900'">
            <span class="flex-1">{{ t.text }}</span>
            <button type="button" class="opacity-60 hover:opacity-100" aria-label="Dismiss" @click="dismiss(t.id)">
                <Icon name="x" :size="12" />
            </button>
        </div>
    </div>
</template>