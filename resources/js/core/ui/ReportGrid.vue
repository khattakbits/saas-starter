<script setup>
import { computed, ref } from 'vue'
import EmptyState from './EmptyState.vue'
import Input from './Input.vue'

const props = defineProps({
    columns: { type: Array, required: true },   // { key, label, width (px), align, type: 'text' | 'number' | 'money', total }
    rows: { type: Array, default: () => [] },
    rowNumbers: { type: Boolean, default: true },
    filterRow: { type: Boolean, default: true },
})

const filters = ref({})
const sort = ref(null)   // { key, dir }

const isNum = c => c.type === 'number' || c.type === 'money'
const fmt = (v, c) => {
    if (!isNum(c)) return v
    const n = Number(v)
    return Number.isNaN(n) ? v : n.toLocaleString('en-US', { minimumFractionDigits: c.type === 'money' ? 2 : 0, maximumFractionDigits: 2 })
}
const toNum = s => { const n = Number(String(s).replace(/,/g, '')); return Number.isNaN(n) ? null : n }

// Numeric columns par: >5  <10  >=5  <=10  =324  5:10
function matchNumber(value, expr, c) {
    const t = expr.trim()
    if (!t) return true
    let m
    if ((m = /^(>=|<=|>|<|=)\s*(-?[\d.,]+)$/.exec(t))) {
        const n = toNum(m[2])
        if (n === null) return true
        return { '>': value > n, '<': value < n, '>=': value >= n, '<=': value <= n, '=': value === n }[m[1]]
    }
    if ((m = /^(-?[\d.,]+)\s*:\s*(-?[\d.,]+)$/.exec(t))) {
        const a = toNum(m[1]), b = toNum(m[2])
        if (a === null || b === null) return true
        return value >= Math.min(a, b) && value <= Math.max(a, b)
    }
    return String(fmt(value, c)).toLowerCase().includes(t.toLowerCase())
}

const visible = computed(() => {
    let list = props.rows.filter(r => props.columns.every(c => {
        const f = filters.value[c.key]
        if (!f || !f.trim()) return true
        return isNum(c) ? matchNumber(Number(r[c.key]), f, c) : String(r[c.key] ?? '').toLowerCase().includes(f.trim().toLowerCase())
    }))
    if (sort.value) {
        const { key, dir } = sort.value
        const col = props.columns.find(c => c.key === key)
        const sign = dir === 'asc' ? 1 : -1
        list = [...list].sort((a, b) => sign * (isNum(col)
            ? Number(a[key]) - Number(b[key])
            : String(a[key] ?? '').localeCompare(String(b[key] ?? ''))))
    }
    return list
})

const hasTotals = computed(() => props.columns.some(c => c.total))
const totalOf = c => visible.value.reduce((s, r) => s + (Number(r[c.key]) || 0), 0)

function toggleSort(c) {
    if (sort.value?.key !== c.key) sort.value = { key: c.key, dir: 'asc' }
    else if (sort.value.dir === 'asc') sort.value = { key: c.key, dir: 'desc' }
    else sort.value = null
}

const th = 'sticky z-10 whitespace-nowrap border-b border-r border-line bg-surface'
</script>

<template>
    <div>
        <table class="w-max min-w-full border-separate border-spacing-0 text-[12px]">
            <thead>
                <tr>
                    <th v-if="rowNumbers" :class="th" class="top-0 h-8 w-10"></th>
                    <th v-for="c in columns" :key="c.key" :class="th" class="top-0 h-8 px-2 font-medium text-ink/80"
                        :style="{ minWidth: (c.width ?? 120) + 'px' }"
                        :aria-sort="sort?.key === c.key ? (sort.dir === 'asc' ? 'ascending' : 'descending') : undefined">
                        <button type="button" class="flex w-full items-center gap-1"
                            :class="c.align === 'right' ? 'justify-end' : ''" @click="toggleSort(c)">
                            {{ c.label }}
                            <span v-if="sort?.key === c.key" class="text-[9px]" aria-hidden="true">{{ sort.dir === 'asc'
                                ? '▲' : '▼' }}</span>
                        </button>
                    </th>
                </tr>
                <tr v-if="filterRow">
                    <th v-if="rowNumbers" :class="th" class="top-8 h-8"></th>
                    <th v-for="c in columns" :key="c.key" :class="th" class="top-8 h-8 px-1.5 font-normal">
                        <Input v-model="filters[c.key]" :aria-label="`Filter ${c.label}`" class="h-6 text-[11px]"
                            :class="c.align === 'right' ? 'text-right' : ''" />
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(r, i) in visible" :key="i" class="hover:bg-accent-soft/50">
                    <td v-if="rowNumbers"
                        class="h-8 border-b border-r border-line/70 text-center tabular-nums text-muted">{{ i + 1 }}
                    </td>
                    <td v-for="c in columns" :key="c.key"
                        class="h-8 whitespace-nowrap border-b border-r border-line/70 px-2"
                        :class="c.align === 'right' ? 'text-right tabular-nums' : ''">
                        <slot :name="`cell-${c.key}`" :row="r" :value="r[c.key]">{{ fmt(r[c.key], c) }}</slot>
                    </td>
                </tr>
            </tbody>
            <tfoot v-if="hasTotals && visible.length">
                <tr>
                    <td v-if="rowNumbers" class="sticky bottom-0 h-8 border-r border-t border-line bg-bg"></td>
                    <td v-for="(c, i) in columns" :key="c.key"
                        class="sticky bottom-0 h-8 whitespace-nowrap border-r border-t border-line bg-bg px-2 font-semibold"
                        :class="c.align === 'right' ? 'text-right tabular-nums' : ''">
                        <template v-if="c.total">{{ fmt(totalOf(c), c) }}</template>
                        <template v-else-if="i === 0">Total</template>
                    </td>
                </tr>
            </tfoot>
        </table>
        <EmptyState v-if="!visible.length" :variant="rows.length ? 'search' : 'report'" compact />
    </div>
</template>