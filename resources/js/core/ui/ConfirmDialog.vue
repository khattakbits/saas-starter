<script setup>
import Modal from './Modal.vue'

const open = defineModel({ type: Boolean, default: false })
defineProps({
    title: String,
    confirmLabel: { type: String, default: 'Confirm' },
    danger: Boolean,
    disabled: Boolean,
})
defineEmits(['confirm'])
</script>

<template>
    <Modal v-model="open" :title="title" width="400px">
        <div class="text-[12px] text-ink/90">
            <slot />
        </div>
        <template #footer>
            <button type="button" class="btn" autofocus @click="open = false">Cancel</button>
            <button type="button" class="btn" :class="danger ? 'btn-danger' : 'btn-primary'" :disabled="disabled"
                @click="$emit('confirm')">
                {{ confirmLabel }}
            </button>
        </template>
    </Modal>
</template>