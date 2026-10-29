<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useActiveModule } from '@core/composables/useActiveModule'
import Icon from '@core/ui/Icon.vue'
import UserMenu from '@core/layouts/UserMenu.vue'
import SubNav from '@core/layouts/SubNav.vue'
import CommandPalette from '@core/layouts/CommandPalette.vue'

const page = usePage()
const appName = computed(() => page.props.app?.name ?? 'App')
const appInitial = computed(() => appName.value.trim().charAt(0).toUpperCase())
const { modules, active } = useActiveModule()
const paletteOpen = ref(false)

// Jis module mein sirf aik hi page ho (jaise Home), us ke liye dusri bar nahi
const itemCount = computed(() => (active.value?.sections ?? []).reduce((n, s) => n + s.items.length, 0))

function onKey(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        paletteOpen.value = true
    }
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
    <div class="flex h-dvh flex-col overflow-hidden bg-bg">
        <!-- Bar 1: brand, modules, search, user. Kabhi scroll nahi hoti -->
        <!-- z-index order: header 40 > sub-nav 30 > page content. Header apna stacking
             context banata hai, is liye user-menu dropdown sub-nav ke oopar paint hota hai. -->
        <header class="relative z-40 flex h-14 flex-none items-stretch border-b border-line bg-surface">
            <div class="flex flex-none items-center gap-2.5 border-r border-line px-4">
                <span
                    class="flex size-7 flex-none items-center justify-center rounded-md bg-accent text-[13px] font-bold text-white">{{
                        appInitial }}</span>
                <span class="truncate text-[15px] font-semibold">{{ appName }}</span>
            </div>

            <nav class="flex items-stretch gap-1 px-3" aria-label="Modules">
                <Link v-for="m in modules" :key="m.key" :href="m.href" prefetch
                    class="relative flex items-center gap-2 px-3.5 text-[14px] hover:text-ink" :class="m.key === active?.key
                        ? 'font-semibold text-accent-dark after:absolute after:inset-x-2 after:bottom-0 after:h-0.75 after:rounded-t after:bg-accent'
                        : 'font-medium text-ink/75'">
                    <Icon :name="m.icon" :size="18" :class="m.key === active?.key ? 'text-accent' : 'text-muted'" />
                    {{ m.label }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-3 px-4">
                <button type="button"
                    class="flex h-9 w-72 items-center gap-2 rounded-md border border-line bg-bg px-3 text-[13px] text-muted hover:border-ink/30"
                    aria-label="Search pages (Ctrl K)" @click="paletteOpen = true">
                    <Icon name="search" :size="15" />
                    <span class="flex-1 text-left">Search pages...</span>
                    <kbd class="rounded border border-line bg-surface px-1.5 py-px text-[11px]">Ctrl K</kbd>
                </button>
                <UserMenu />
            </div>
        </header>

        <!-- Bar 2: active module ke Doctypes / Reports / Settings -->
        <SubNav v-if="itemCount > 1" />

        <!-- Content: page-type component apna scroll sambhalta hai -->
        <main class="min-h-0 min-w-0 flex-1 overflow-hidden">
            <div class="flex h-full min-h-0 flex-col p-4">
                <slot />
            </div>
        </main>

        <CommandPalette v-model="paletteOpen" />
    </div>
</template>