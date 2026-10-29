<script setup>
import { useId } from 'vue'

defineProps({
    label: String,
    error: String,
    hint: String,
    required: Boolean,
    size: { type: String, default: 'full' },   // xs | sm | md | lg | full
    span: { type: Number, default: 1 },        // grid mein kitne columns
})
const id = useId()

const spans = { 1: '', 2: 'col-span-2', 3: 'col-span-3', 4: 'col-span-4' }
const widths = { xs: 'max-w-32', sm: 'max-w-48', md: 'max-w-64', lg: 'max-w-96', full: '' }
</script>

<template>
    <div class="flex flex-col gap-1.5" :class="spans[span]">
        <label :for="id" class="text-[12px] font-semibold text-ink/80">
            {{ label }}<span v-if="required" class="text-red-600"> *</span>
        </label>
        <div :class="widths[size]">
            <slot :id="id" :invalid="!!error" />
        </div>
        <p v-if="error" :id="id + '-err'" role="alert" class="text-[12px] text-red-700">{{ error }}</p>
        <p v-else-if="hint" class="text-[11px] text-muted">{{ hint }}</p>
    </div>
</template>