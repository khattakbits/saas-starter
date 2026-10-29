<script setup>
import { Link } from '@inertiajs/vue3'
import Icon from '@core/ui/Icon.vue'

defineProps({ title: String, description: String, back: String, backLabel: { type: String, default: 'Back' } })
</script>

<template>
    <header class="flex min-h-10 flex-none items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="truncate text-[15px] font-semibold leading-6">{{ title }}</h1>
                <slot name="badge" />
            </div>
            <p v-if="description" class="truncate leading-4 text-muted">{{ description }}</p>
        </div>
        <div class="flex flex-none items-center gap-2">
            <Link v-if="back" :href="back" class="btn" :title="`Back to ${backLabel}`">
                <Icon name="chevron-left" :size="16" />Back
            </Link>
            <slot name="actions" />
        </div>
    </header>
</template>