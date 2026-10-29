<script setup>
import { computed, onBeforeUnmount, onMounted, nextTick, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useActiveModule } from '@core/composables/useActiveModule'
import Icon from '@core/ui/Icon.vue'

const page = usePage()
const { active, isItemActive } = useActiveModule()

const ICONS = { Doctypes: 'layers', Reports: 'chart', Settings: 'sliders' }
const WIDTH = { 1: 'w-72', 2: 'w-[34rem]', 3: 'w-[48rem]' }
const COLS = { 1: 'columns-1', 2: 'columns-2 gap-x-6', 3: 'columns-3 gap-x-6' }

const root = ref(null)
const openKey = ref(null)
const query = ref('')
const triggers = {}

const sections = computed(() => (active.value?.sections ?? []).map(s => {
    const groups = []
    for (const item of s.items) {
        const name = item.group ?? ''
        let g = groups.find(x => x.name === name)
        if (!g) groups.push((g = { name, items: [] }))
        g.items.push(item)
    }
    groups.sort((a, b) => (a.name === '' ? 0 : 1) - (b.name === '' ? 0 : 1))   // bagair group wale pehle

    return {
        title: s.title,
        icon: ICONS[s.title] ?? 'grid',
        items: s.items,
        groups,
        current: s.items.find(i => isItemActive(i.href)),
        single: s.items.length === 1,
    }
}))

const colsFor = s => (s.items.length > 24 ? 3 : s.items.length > 10 ? 2 : 1)
const panelId = s => `subnav-${s.title.toLowerCase().replace(/\W+/g, '-')}`

function visibleGroups(s) {
    const t = query.value.trim().toLowerCase()
    if (!t) return s.groups
    return s.groups
        .map(g => ({ ...g, items: g.items.filter(i => `${i.label} ${g.name}`.toLowerCase().includes(t)) }))
        .filter(g => g.items.length)
}

async function toggle(s) {
    if (openKey.value === s.title) return close()
    openKey.value = s.title
    query.value = ''
    await nextTick()
    root.value?.querySelector('[data-panel] input[type=search]')?.focus()
}

function close(returnFocus = false) {
    const key = openKey.value
    openKey.value = null
    if (returnFocus && key) triggers[key]?.focus()
}

function openFirst(s) {
    const first = visibleGroups(s)[0]?.items[0]
    if (first) router.visit(first.href)
}

function onPanelKey(e) {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return
    const links = [...e.currentTarget.querySelectorAll('a[data-nav-item]')]
    if (!links.length) return
    e.preventDefault()
    const i = links.indexOf(document.activeElement)
    const next = e.key === 'ArrowDown' ? (i + 1) % links.length : i <= 0 ? links.length - 1 : i - 1
    links[next].focus()
}

const onPointer = e => { if (openKey.value && root.value && !root.value.contains(e.target)) close() }
const onEsc = e => {
    if (e.key === 'Escape' && openKey.value) { e.stopPropagation(); close(true) }
}

onMounted(() => {
    document.addEventListener('pointerdown', onPointer)
    document.addEventListener('keydown', onEsc)
})
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointer)
    document.removeEventListener('keydown', onEsc)
})

watch(() => page.url, () => close())
watch(() => active.value?.key, () => close())
</script>

<template>
    <nav v-if="active" ref="root" :aria-label="`${active.label} pages`"
        class="relative z-30 flex h-12 flex-none justify-center items-center gap-2 bg-surface px-4">
        <template v-for="s in sections" :key="s.title">
            <!-- aik hi item wala section: seedha link -->
            <Link v-if="s.single" :href="s.items[0].href" prefetch class="nav-btn gap-2"
                :aria-current="isItemActive(s.items[0].href) ? 'page' : undefined">
                <Icon :name="s.icon" :size="16" class="text-muted" />
                <span class="font-normal text-muted">{{ s.title }}</span>
                <span class="h-4 w-px bg-line" aria-hidden="true"></span>
                <span>{{ s.items[0].label }}</span>
            </Link>

            <!-- baqi sections: button + panel -->
            <div v-else class="relative">
                <button :ref="el => { if (el) triggers[s.title] = el }" type="button" class="nav-btn gap-2"
                    :data-active="!!s.current" :aria-expanded="openKey === s.title"
                    :aria-controls="openKey === s.title ? panelId(s) : undefined" @click="toggle(s)">
                    <Icon :name="s.icon" :size="16" :class="s.current ? 'text-accent' : 'text-muted'" />
                    <span>{{ s.title }}</span>
                    <template v-if="s.current">
                        <span class="h-4 w-px bg-accent/40" aria-hidden="true"></span>
                        <span class="font-semibold">{{ s.current.label }}</span>
                    </template>
                    <Icon name="chevron-down" :size="14" class="text-muted transition-transform"
                        :class="openKey === s.title ? 'rotate-180' : ''" />
                </button>

                <div v-if="openKey === s.title" :id="panelId(s)" data-panel
                    class="absolute left-0 top-full mt-1.5 max-w-[calc(100vw-2rem)] rounded-lg border border-line bg-surface p-2 shadow-xl"
                    :class="WIDTH[colsFor(s)]" @keydown="onPanelKey">
                    <div v-if="s.items.length > 8" class="mb-2">
                        <input v-model="query" type="search" :placeholder="`Filter ${s.title.toLowerCase()}...`"
                            :aria-label="`Filter ${s.title}`" class="field" @keydown.enter.prevent="openFirst(s)" />
                    </div>

                    <div class="noscroll max-h-[60vh] overflow-y-auto">
                        <div :class="COLS[colsFor(s)]">
                            <section v-for="g in visibleGroups(s)" :key="g.name" class="mb-3"
                                :class="g.items.length <= 12 ? 'break-inside-avoid' : ''">
                                <h3 v-if="g.name"
                                    class="px-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted">{{
                                        g.name }}</h3>
                                <ul>
                                    <li v-for="item in g.items" :key="item.href">
                                        <Link :href="item.href" data-nav-item
                                            :aria-current="isItemActive(item.href) ? 'page' : undefined"
                                            class="flex h-8 items-center justify-between gap-2 rounded-md px-2.5 text-[13px] outline-none focus-visible:ring-2 focus-visible:ring-accent"
                                            :class="isItemActive(item.href) ? 'bg-accent-soft font-semibold text-accent-dark' : 'text-ink/85 hover:bg-bg hover:text-ink'"
                                            @click="close()">
                                            <span class="truncate">{{ item.label }}</span>
                                            <Icon v-if="isItemActive(item.href)" name="check" :size="14" />
                                        </Link>
                                    </li>
                                </ul>
                            </section>
                        </div>
                        <p v-if="!visibleGroups(s).length" class="px-2.5 py-3 text-muted">No matching pages</p>
                    </div>
                </div>
            </div>
        </template>
    </nav>
</template>