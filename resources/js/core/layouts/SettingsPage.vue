<script setup>
import FormPage from './FormPage.vue'

defineProps({ title: String, description: String, back: String, backLabel: String })
</script>

<template>
    <FormPage v-bind="$props">
        <template v-for="(_, name) in $slots" #[name]="slotProps" :key="name">
            <slot :name="name" v-bind="slotProps ?? {}" />
        </template>
    </FormPage>
</template>