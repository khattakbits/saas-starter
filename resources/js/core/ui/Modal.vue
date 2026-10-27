<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
    modelValue: Boolean,
    title: String,
    width: { type: String, default: '440px' },
    closeOnBackdrop: Boolean,   // forms mein band rakhein: bhara hua data ghalti se na ude
})
const emit = defineEmits(['update:modelValue'])

const dlg = ref(null)
const closing = ref(false)
let timer

function show() {
    const d = dlg.value
    if (!d) return
    clearTimeout(timer)
    closing.value = false
    if (!d.open) d.showModal()
}

function hide() {
    const d = dlg.value
    if (!d || !d.open || closing.value) return
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { d.close(); return }
    closing.value = true                       // band hone ki animation (180ms), phir asli close
    timer = setTimeout(() => { closing.value = false; d.close() }, 180)
}

const requestClose = () => emit('update:modelValue', false)

onMounted(() => { if (props.modelValue) show() })
watch(() => props.modelValue, v => (v ? show() : hide()))
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
    <dialog ref="dlg" :class="{ closing }"
        class="modal w-full overflow-visible rounded-[10px] border border-line bg-surface p-0 text-ink shadow-xl backdrop:bg-black/40"
        :style="{ maxWidth: width }" @cancel.prevent="requestClose" @close="requestClose"
        @click="e => closeOnBackdrop && e.target === dlg && requestClose()">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <h2 class="text-[13px] font-semibold">{{ title }}</h2>
            <button type="button" class="btn btn-icon" aria-label="Close" @click="requestClose">
                <Icon name="x" :size="13" />
            </button>
        </div>
        <div class="px-4 py-4">
            <slot />
        </div>
        <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line px-4 py-3">
            <slot name="footer" />
        </div>
    </dialog>
</template>