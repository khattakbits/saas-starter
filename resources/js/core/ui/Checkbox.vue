<script setup>
defineProps({ id: String, label: String, disabled: Boolean })
const model = defineModel({ type: Boolean, default: false })
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-2 text-[12px]"
        :class="disabled ? 'cursor-not-allowed opacity-60' : ''">
        <input :id="id" v-model="model" type="checkbox" :disabled="disabled" class="size-3.5 accent-accent" />
        <slot>{{ label }}</slot>
    </label>
</template>