<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import EmptyState from '@core/ui/EmptyState.vue'

const props = defineProps({ status: Number })

const variant = computed(() => ({ 403: 'forbidden', 404: 'notfound' }[props.status] ?? 'error'))
const copy = computed(() => ({
    500: ['Server error', 'Something went wrong on our side.'],
    503: ['Under maintenance', 'We will be back shortly.'],
}[props.status] ?? []))
</script>

<template>
    <div class="flex min-h-dvh items-center justify-center p-4">
        <EmptyState :variant="variant" :title="copy[0]" :description="copy[1]">
            <Link href="/" class="btn btn-primary">Go to dashboard</Link>
        </EmptyState>
    </div>
</template>