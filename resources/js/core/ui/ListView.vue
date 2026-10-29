<script setup>
import { computed, nextTick, ref, shallowRef, useId, watch } from 'vue'
import { useVirtualizer } from '@tanstack/vue-virtual'
import EmptyState from './EmptyState.vue'

const props = defineProps({
    columns: { type: Array, required: true },    // { key, label, width, align, sort }
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    label: { type: String, default: 'Records' },
    total: Number,
    totalApprox: Boolean,
    loading: Boolean,
    error: Boolean,
    filtered: Boolean,
    hasMore: Boolean,
    loadingMore: Boolean,
    pageSize: { type: Number, default: 20 },
    pageSizes: { type: Array, default: () => [20, 100, 500, 2500] },
    selectable: { type: Boolean, default: true },
    clickable: { type: Boolean, default: true },
    endWidth: { type: String, default: '120px' },
    rowHeight: { type: Number, default: 36 },
    sort: Object,                                // { key, dir }
    resetKey: String,                            // badle to scroll upar aur selection saaf
})
const emit = defineEmits(['row-click', 'update:pageSize', 'update:sort', 'load-more', 'retry', 'clear-filters'])

const uid = useId()
const rowId = i => `${uid}-row-${i}`

const gridCols = computed(() =>
    `${props.selectable ? '28px ' : ''}${props.columns.map(c => c.width ?? '1fr').join(' ')} ${props.endWidth}`)

const countText = computed(() =>
    props.total == null
        ? `${props.rows.length.toLocaleString()} loaded`
        : `${props.rows.length.toLocaleString()} of ${props.total.toLocaleString()}${props.totalApprox ? '+' : ''}`)

/* ---------- sort ---------- */
function toggleSort(c) {
    const cur = props.sort
    emit('update:sort', cur?.key === c.sort ? { key: c.sort, dir: cur.dir === 'asc' ? 'desc' : 'asc' } : { key: c.sort, dir: 'asc' })
}
const ariaSort = c => (c.sort && props.sort?.key === c.sort ? (props.sort.dir === 'asc' ? 'ascending' : 'descending') : undefined)

/* ---------- virtualization ---------- */
const scroller = ref(null)
const virt = useVirtualizer(computed(() => ({
    count: props.rows.length,
    getScrollElement: () => scroller.value,
    estimateSize: () => props.rowHeight,
    overscan: 10,
    getItemKey: i => props.rows[i]?.[props.rowKey] ?? i,
})))
const items = computed(() =>
    virt.value.getVirtualItems()
        .filter(v => props.rows[v.index])
        .map(v => ({ key: v.key, index: v.index, start: v.start, size: v.size, row: props.rows[v.index] })))

/* ---------- keyboard: sirf aik row tabbable, baqi arrow keys se ---------- */
const focusIndex = ref(0)
const tabbableIndex = computed(() =>
    items.value.some(i => i.index === focusIndex.value) ? focusIndex.value : items.value[0]?.index)

async function focusRow(i) {
    const last = props.rows.length - 1
    if (last < 0) return
    const next = Math.max(0, Math.min(last, i))
    focusIndex.value = next
    virt.value.scrollToIndex(next)
    await nextTick()
    await new Promise(r => requestAnimationFrame(r))
    document.getElementById(rowId(next))?.focus()
}

function onRowKey(e, it) {
    if (e.target !== e.currentTarget) return   // checkbox par key events nahi
    switch (e.key) {
        case 'ArrowDown': e.preventDefault(); focusRow(it.index + 1); break
        case 'ArrowUp': e.preventDefault(); focusRow(it.index - 1); break
        case 'Home': e.preventDefault(); focusRow(0); break
        case 'End': e.preventDefault(); focusRow(props.rows.length - 1); break
        case 'Enter': if (props.clickable) emit('row-click', it.row); break
        case ' ': if (props.selectable) { e.preventDefault(); toggle(it.index, e) } break
    }
}

/* ---------- selection ---------- */
const selected = shallowRef(new Set())
let lastIndex = null
const idOf = row => row[props.rowKey]
const isSelected = row => selected.value.has(idOf(row))
const allSelected = computed(() => props.rows.length > 0 && selected.value.size === props.rows.length)
const someSelected = computed(() => selected.value.size > 0 && !allSelected.value)

function clearSelection() { selected.value = new Set(); lastIndex = null }

function toggle(index, e) {
    const next = new Set(selected.value)
    const on = !next.has(idOf(props.rows[index]))
    const ranged = e.shiftKey && lastIndex !== null
    const from = ranged ? Math.min(lastIndex, index) : index
    const to = ranged ? Math.max(lastIndex, index) : index
    for (let i = from; i <= to; i++) {
        const id = idOf(props.rows[i])
        on ? next.add(id) : next.delete(id)
    }
    lastIndex = index
    selected.value = next
}

function toggleAll() {
    selected.value = allSelected.value ? new Set() : new Set(props.rows.map(idOf))
    lastIndex = null
}

watch(() => props.resetKey, () => {
    if (scroller.value) scroller.value.scrollTop = 0
    focusIndex.value = 0
    clearSelection()
})

// Jo rows ab nazar nahi aa rahin (delete/filter) unki selection hat jaye
watch(() => props.rows, rows => {
    if (!selected.value.size) return
    const present = new Set(rows.map(idOf))
    const next = new Set([...selected.value].filter(id => present.has(id)))
    if (next.size !== selected.value.size) selected.value = next
})

defineExpose({ clearSelection })
</script>

<template>
    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-line bg-surface">
        <!-- filter bar; selection ho to bulk bar -->
        <div class="flex min-h-12 flex-none items-center gap-2 border-b border-line px-3 py-2">
            <template v-if="selected.size">
                <span class="font-semibold text-accent-dark">
                    {{ selected.size.toLocaleString() }}<template v-if="total != null"> of {{ total.toLocaleString()
                    }}</template>
                    selected
                </span>
                <span v-if="allSelected && hasMore" class="text-[11px] text-muted">Only the loaded rows are
                    selected</span>
                <slot name="bulk" :ids="[...selected]" :clear="clearSelection" />
                <button type="button" class="btn ml-auto" @click="clearSelection">Clear</button>
            </template>
            <template v-else>
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <slot name="filters" />
                </div>
                <div class="flex flex-none items-center gap-2">
                    <slot name="filter-actions" />
                </div>
            </template>
        </div>

        <div role="table" :aria-label="label" :aria-rowcount="hasMore ? -1 : rows.length + 1"
            class="flex min-h-0 flex-1 flex-col">
            <div role="rowgroup" class="flex-none">
                <div role="row" aria-rowindex="1"
                    class="group grid h-8 items-center border-b border-line bg-bg px-3 text-[11px] font-semibold text-muted"
                    :style="{ gridTemplateColumns: gridCols }">
                    <span v-if="selectable" role="columnheader" aria-label="Select" class="flex items-center">
                        <input type="checkbox" class="size-3 accent-accent" aria-label="Select all loaded rows"
                            :checked="allSelected" :indeterminate="someSelected" :disabled="!rows.length"
                            @change="toggleAll" />
                    </span>
                    <div v-for="c in columns" :key="c.key" role="columnheader" class="min-w-0" :aria-sort="ariaSort(c)">
                        <button v-if="c.sort" type="button" class="flex max-w-full items-center gap-1 hover:text-ink"
                            :class="c.align === 'right' ? 'ml-auto' : ''" @click="toggleSort(c)">
                            <span class="truncate">{{ c.label }}</span>
                            <span v-if="sort?.key === c.sort" class="text-[9px]" aria-hidden="true">{{ sort.dir ===
                                'asc' ? '▲'
                                : '▼' }}</span>
                        </button>
                        <span v-else class="block truncate" :class="c.align === 'right' ? 'text-right' : ''">{{ c.label
                        }}</span>
                    </div>
                    <span role="columnheader" class="text-right font-normal">{{ countText }}</span>
                </div>
            </div>

            <!-- rows: sirf yehi scroll karta hai -->
            <div ref="scroller" class="noscroll min-h-0 flex-1 overflow-y-auto">
                <div v-if="loading">
                    <div v-for="n in 10" :key="n" class="grid items-center border-b border-line/60 px-3"
                        :style="{ height: rowHeight + 'px', gridTemplateColumns: gridCols }">
                        <span v-if="selectable"></span>
                        <span v-for="c in columns" :key="c.key" class="h-2.5 w-2/3 rounded bg-line/70"></span>
                        <span></span>
                    </div>
                </div>

                <EmptyState v-else-if="error" variant="error">
                    <button type="button" class="btn" @click="emit('retry')">Retry</button>
                </EmptyState>

                <EmptyState v-else-if="!rows.length" :variant="filtered ? 'search' : 'records'">
                    <button v-if="filtered" type="button" class="btn" @click="emit('clear-filters')">Clear
                        filters</button>
                    <slot v-else name="empty-actions" />
                </EmptyState>

                <div v-else role="rowgroup" class="relative" :style="{ height: virt.getTotalSize() + 'px' }">
                    <div v-for="it in items" :id="rowId(it.index)" :key="it.key" role="row"
                        :aria-rowindex="it.index + 2" :tabindex="it.index === tabbableIndex ? 0 : -1"
                        class="group absolute left-0 top-0 grid w-full items-center border-b border-line/60 px-3 outline-none focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-accent"
                        :class="[clickable ? 'cursor-pointer' : '', isSelected(it.row) ? 'bg-accent-soft' : clickable ? 'hover:bg-accent-soft/60' : 'hover:bg-bg/60']"
                        :style="{ height: it.size + 'px', transform: `translateY(${it.start}px)`, gridTemplateColumns: gridCols }"
                        @click="clickable && emit('row-click', it.row)" @focus="focusIndex = it.index"
                        @keydown="onRowKey($event, it)">
                        <span v-if="selectable" role="cell" class="flex items-center" @click.stop>
                            <input type="checkbox" class="size-3 accent-accent"
                                :aria-label="`Select row ${it.index + 1}`" :checked="isSelected(it.row)"
                                @click="toggle(it.index, $event)" />
                        </span>
                        <span v-for="c in columns" :key="c.key" role="cell" class="truncate"
                            :class="c.align === 'right' ? 'text-right' : ''">
                            <slot :name="`cell-${c.key}`" :row="it.row" :value="it.row[c.key]">{{ it.row[c.key] }}
                            </slot>
                        </span>
                        <span role="cell" class="flex items-center justify-end gap-3 text-[11px] text-muted">
                            <slot name="row-end" :row="it.row" />
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- footer: page size aur load more -->
        <div class="flex h-12 flex-none items-center justify-between border-t border-line px-3">
            <div role="group" aria-label="Rows per page" class="flex overflow-hidden rounded-md border border-line">
                <button v-for="n in pageSizes" :key="n" type="button" :aria-pressed="n === pageSize"
                    class="h-7 min-w-10 border-r border-line px-2.5 text-[12px] last:border-r-0"
                    :class="n === pageSize ? 'bg-accent font-semibold text-white' : 'bg-surface hover:bg-bg'"
                    @click="emit('update:pageSize', n)">{{ n }}</button>
            </div>
            <button v-if="hasMore" type="button" class="btn" :disabled="loadingMore" @click="emit('load-more')">
                {{ loadingMore ? 'Loading...' : 'Load more' }}
            </button>
        </div>
    </section>
</template>