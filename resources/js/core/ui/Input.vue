<script setup>
defineProps({ id: String, type: { type: String, default: 'text' }, invalid: Boolean, placeholder: String })
const model = defineModel()
</script>

<template>
    <input :id="id" v-model="model" :type="type" :placeholder="placeholder" class="field"
        :aria-invalid="invalid || undefined" :aria-describedby="invalid ? id + '-err' : undefined" />
</template>