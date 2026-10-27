<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Icon from '@core/ui/Icon.vue'

const page = usePage()
const auth = computed(() => page.props.auth)
const initial = computed(() => (auth.value?.name ?? '?').trim().charAt(0).toUpperCase())
const open = ref(false)
const root = ref(null)

const onPointer = e => { if (open.value && root.value && !root.value.contains(e.target)) open.value = false }
const onKey = e => {
    if (e.key === 'Escape' && open.value) {
        open.value = false
        root.value?.querySelector('button')?.focus()
    }
}
onMounted(() => { document.addEventListener('pointerdown', onPointer); document.addEventListener('keydown', onKey) })
onBeforeUnmount(() => { document.removeEventListener('pointerdown', onPointer); document.removeEventListener('keydown', onKey) })
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" class="flex h-8 items-center gap-2 rounded-md px-1.5 hover:bg-bg" aria-haspopup="menu"
            :aria-expanded="open" @click="open = !open">
            <span
                class="flex size-6 items-center justify-center rounded-full bg-accent text-[11px] font-semibold text-white">{{
                initial }}</span>
            <span class="max-w-32 truncate text-ink/90">{{ auth?.name }}</span>
            <Icon name="chevron-down" :size="14" class="text-muted" />
        </button>

        <div v-show="open" role="menu"
            class="absolute right-0 top-full z-30 mt-1 w-56 rounded-lg border border-line bg-surface py-1 shadow-lg">
            <div class="border-b border-line px-3 py-2">
                <p class="truncate font-semibold">{{ auth?.name }}</p>
                <p class="truncate text-muted">{{ auth?.email }}</p>
            </div>
            <Link href="/profile" role="menuitem" class="flex h-8 items-center gap-2 px-3 hover:bg-bg"
                @click="open = false">
                <Icon name="user" :size="14" />Profile
            </Link>
            <div class="my-1 border-t border-line"></div>
            <Link href="/logout" method="post" as="button" role="menuitem"
                class="flex h-8 w-full items-center gap-2 px-3 text-left hover:bg-bg">
                <Icon name="log-out" :size="14" />Logout
            </Link>
        </div>
    </div>
</template>