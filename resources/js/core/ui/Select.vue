<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue'
import { useFloating } from '@core/composables/useFloating'
import Icon from './Icon.vue'

const props = defineProps({
    id: String,
    options: { type: Array, default: () => [] },   // { value, label, description? }
    endpoint: String,                              // server search: GET ?q= -> { data: [...] }
    display: String,                               // endpoint ke saath: pehle se chuni value ka label
    placeholder: { type: String, default: 'Select...' },
    clearable: Boolean,
    invalid: Boolean,
    disabled: Boolean,
})
const model = defineModel()

const uid = useId()
const listId = `${uid}-list`
const anchor = ref(null)
const input = ref(null)
const open = ref(false)
const text = ref('')
const query = ref('')
const active = ref(-1)
const remote = ref([])
const loading = ref(false)
const picked = ref(props.display ?? '')
const { style: floatStyle, place, start, stop } = useFloating(input, { maxHeight: 280 })

const same = (a, b) => String(a) === String(b)
const hasValue = computed(() => model.value !== '' && model.value != null)
const pool = computed(() => (props.endpoint ? remote.value : props.options))

function labelOf(value) {
    if (value === '' || value == null) return ''
    const hit = props.options.find(o => same(o.value, value)) ?? remote.value.find(o => same(o.value, value))
    return hit ? hit.label : picked.value
}

const filtered = computed(() => {
    if (props.endpoint) return remote.value
    const t = query.value.trim().toLowerCase()
    if (!t) return props.options
    return props.options.filter(o => `${o.label} ${o.description ?? ''}`.toLowerCase().includes(t))
})

watch([model, () => props.options], () => { if (!open.value) text.value = labelOf(model.value) }, { immediate: true })
watch(active, async i => {
    await nextTick()
    document.getElementById(`${listId}-${i}`)?.scrollIntoView({ block: 'nearest' })
})

let timer, ctrl
async function load() {
    ctrl?.abort()
    ctrl = new AbortController()
    loading.value = true
    try {
        const res = await fetch(`${props.endpoint}?q=${encodeURIComponent(query.value.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: ctrl.signal,
        })
        if (res.status === 401 || res.status === 419) { window.location.href = '/login'; return }
        remote.value = res.ok ? (await res.json()).data : []
        active.value = remote.value.length ? 0 : -1
        loading.value = false
    } catch (e) {
        if (e.name !== 'AbortError') { remote.value = []; loading.value = false }
    }
}

async function openList() {
    if (props.disabled || open.value) return
    open.value = true
    query.value = ''
    if (props.endpoint) load()
    const i = filtered.value.findIndex(o => same(o.value, model.value))
    active.value = i >= 0 ? i : filtered.value.length ? 0 : -1
    await nextTick()
    place(Math.min(280, filtered.value.length * 44 + 8))
    start()
}

function closeList() {
    open.value = false
    stop()
}

function onFocus() {
    input.value?.select()
    openList()
}

function onBlur() {
    closeList()
    if (props.clearable && text.value.trim() === '' && hasValue.value) {
        model.value = ''
        picked.value = ''
    }
    text.value = labelOf(model.value)
}

function onInput() {
    query.value = text.value
    if (!open.value) openList()
    if (props.endpoint) {
        clearTimeout(timer)
        timer = setTimeout(load, 200)
    } else {
        active.value = filtered.value.length ? 0 : -1
    }
    nextTick(() => place(Math.min(280, filtered.value.length * 44 + 8)))
}

function choose(o) {
    model.value = o.value
    picked.value = o.label
    text.value = o.label
    closeList()
}

function clear() {
    model.value = ''
    picked.value = ''
    text.value = ''
    query.value = ''
    if (open.value && props.endpoint) load()
}

function onKey(e) {
    const n = filtered.value.length
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        if (!open.value) openList()
        else if (n) active.value = (active.value + 1) % n
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        if (n) active.value = (active.value - 1 + n) % n
    } else if (e.key === 'Enter' && open.value && active.value >= 0 && filtered.value[active.value]) {
        e.preventDefault()          // form submit na ho
        choose(filtered.value[active.value])
    } else if (e.key === 'Escape' && open.value) {
        e.preventDefault()
        e.stopPropagation()         // modal band na ho, sirf list band ho
        closeList()
        text.value = labelOf(model.value)
    } else if (e.key === 'Tab') {
        closeList()
    }
}
</script>

<template>
    <div ref="anchor" class="relative">
        <input :id="id" ref="input" v-model="text" type="text" role="combobox" autocomplete="off" spellcheck="false"
            class="field truncate" :class="clearable && hasValue ? 'pr-12' : 'pr-7'" :placeholder="placeholder"
            :disabled="disabled" :aria-expanded="open" :aria-controls="listId" aria-autocomplete="list"
            :aria-activedescendant="open && active >= 0 ? `${listId}-${active}` : undefined"
            :aria-invalid="invalid || undefined" :aria-describedby="invalid && id ? id + '-err' : undefined"
            @focus="onFocus" @click="openList" @blur="onBlur" @input="onInput" @keydown="onKey" />

        <button v-if="clearable && hasValue && !disabled" type="button" tabindex="-1" aria-label="Clear"
            class="absolute right-6 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted hover:text-ink"
            @mousedown.prevent="clear">
            <Icon name="x" :size="12" />
        </button>
        <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-muted">
            <Icon name="chevron-down" :size="14" />
        </span>

        <ul v-show="open" :id="listId" role="listbox" :style="floatStyle"
            class="noscroll fixed z-50 overflow-y-auto rounded-lg border border-line bg-surface py-1 shadow-lg">
            <li v-for="(o, i) in filtered" :id="`${listId}-${i}`" :key="o.value" role="option"
                :aria-selected="same(o.value, model)" class="cursor-pointer px-3 py-1.5"
                :class="i === active ? 'bg-bg' : ''" @mousemove="active = i" @mousedown.prevent="choose(o)">
                <div class="truncate" :class="same(o.value, model) ? 'font-semibold text-accent-dark' : 'font-medium'">
                    {{ o.label }}</div>
                <div v-if="o.description" class="truncate text-[11px] text-muted">{{ o.description }}</div>
            </li>
            <li v-if="loading" class="px-3 py-2 text-muted">Searching...</li>
            <li v-else-if="!filtered.length" class="px-3 py-2 text-muted">No results</li>
        </ul>
    </div>
</template>