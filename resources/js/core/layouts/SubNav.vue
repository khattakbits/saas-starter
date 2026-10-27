<script setup>
import { nextTick, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useActiveModule } from '@core/composables/useActiveModule'

const { active, isItemActive } = useActiveModule()
const scroller = ref(null)

// Mouse wheel se bhi horizontal scroll
function onWheel(e) {
    const el = scroller.value
    if (!el || el.scrollWidth <= el.clientWidth || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return
    e.preventDefault()
    el.scrollLeft += e.deltaY
}

// Active item nazar ke saamne rahe
async function reveal() {
    await nextTick()
    scroller.value?.querySelector('[aria-current="page"]')?.scrollIntoView({ inline: 'nearest', block: 'nearest' })
}
onMounted(reveal)
watch(() => active.value?.key, reveal)
</script>

<template>
    <nav v-if="active" :aria-label="`${active.label} pages`"
        class="flex h-11 flex-none items-stretch border-b border-line bg-surface">
        <div ref="scroller" class="noscroll flex min-w-0 flex-1 items-center overflow-x-auto px-4" @wheel="onWheel">
            <template v-for="(section, si) in active.sections" :key="section.title">
                <span v-if="si" class="mx-3 h-5 w-px flex-none bg-line" aria-hidden="true"></span>
                <div class="flex flex-none items-center gap-1.5" role="group" :aria-label="section.title">
                    <span
                        class="flex-none rounded bg-bg px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted">{{
                        section.title }}</span>
                    <Link v-for="item in section.items" :key="item.href" :href="item.href" prefetch class="nav-btn"
                        :aria-current="isItemActive(item.href) ? 'page' : undefined">
                        {{ item.label }}
                    </Link>
                </div>
            </template>
        </div>
    </nav>
</template>