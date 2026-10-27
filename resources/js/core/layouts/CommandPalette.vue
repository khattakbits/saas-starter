<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useActiveModule } from '@core/composables/useActiveModule'
import Icon from '@core/ui/Icon.vue'

const open = defineModel({ type: Boolean, default: false })
const { modules } = useActiveModule()
const dlg = ref(null)
const input = ref(null)
const q = ref('')
const active = ref(0)

// Menu ke saare pages (sirf wohi jin ka user ko haq hai)
const all = computed(() => modules.value.flatMap(m =>
    m.sections.flatMap(s => s.items.map(i => ({ label: i.label, href: i.href, module: m.label, section: s.title })))))

const results = computed(() => {
    const t = q.value.trim().toLowerCase()
    const list = t ? all.value.filter(i => `${i.label} ${i.module} ${i.section}`.toLowerCase().includes(t)) : all.value
    return list.slice(0, 40)
})

watch(open, async v => {
    const d = dlg.value
    if (!d) return
    if (v && !d.open) {
        q.value = ''
        active.value = 0
        d.showModal()
        await nextTick()
        input.value?.focus()
    } else if (!v && d.open) d.close()
})
watch(q, () => (active.value = 0))
watch(active, async i => {
    await nextTick()
    document.getElementById(`cmd-opt-${i}`)?.scrollIntoView({ block: 'nearest' })
})

function go(item) {
    if (!item) return
    open.value = false
    router.visit(item.href)
}

function onKey(e) {
    const n = results.value.length
    if (e.key === 'ArrowDown') { e.preventDefault(); if (n) active.value = (active.value + 1) % n }
    else if (e.key === 'ArrowUp') { e.preventDefault(); if (n) active.value = (active.value - 1 + n) % n }
    else if (e.key === 'Enter') { e.preventDefault(); go(results.value[active.value]) }
}
</script>

<template>
    <dialog ref="dlg" aria-label="Search pages"
        class="modal w-full max-w-lg overflow-hidden rounded-xl border border-line bg-surface p-0 text-ink shadow-2xl backdrop:bg-black/40"
        @close="open = false" @click="e => e.target === dlg && dlg.close()">
        <div class="flex items-center gap-2 border-b border-line px-3">
            <Icon name="search" :size="16" class="text-muted" />
            <input ref="input" v-model="q" type="text" role="combobox" autocomplete="off" placeholder="Search pages..."
                class="h-11 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted"
                aria-controls="cmd-list" aria-expanded="true" aria-autocomplete="list"
                :aria-activedescendant="results.length ? `cmd-opt-${active}` : undefined" @keydown="onKey" />
            <kbd class="rounded border border-line bg-bg px-1.5 py-px text-[10px] text-muted">Esc</kbd>
        </div>
        <ul id="cmd-list" role="listbox" class="noscroll max-h-80 overflow-y-auto p-1.5">
            <li v-for="(r, i) in results" :id="`cmd-opt-${i}`" :key="r.href" role="option" :aria-selected="i === active"
                class="flex h-9 cursor-pointer items-center justify-between rounded-md px-2.5"
                :class="i === active ? 'bg-accent-soft text-accent-dark' : ''" @mousemove="active = i" @click="go(r)">
                <span class="font-medium">{{ r.label }}</span>
                <span class="text-[11px] text-muted">{{ r.module }} · {{ r.section }}</span>
            </li>
            <li v-if="!results.length" class="px-3 py-6 text-center text-muted">No matching pages</li>
        </ul>
    </dialog>
</template>