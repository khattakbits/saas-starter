<script setup>
import { computed, ref } from 'vue'

const props = defineProps({ groups: { type: Array, required: true }, readonly: Boolean })
const model = defineModel({ type: Array, default: () => [] })

const q = ref('')
const granted = computed(() => new Set(model.value))
const keyOf = (row, a) => `${row.key}.${a}`
const cap = s => s.charAt(0).toUpperCase() + s.slice(1)
const rowNames = row => row.actions.map(a => keyOf(row, a))

const visibleRows = g => {
    const t = q.value.trim().toLowerCase()
    return t ? g.rows.filter(r => `${g.label} ${r.label} ${r.key}`.toLowerCase().includes(t)) : g.rows
}
const visibleGroups = computed(() => props.groups.filter(g => visibleRows(g).length))
const groupNames = g => visibleRows(g).flatMap(rowNames)
const countIn = names => names.filter(n => granted.value.has(n)).length

const commit = next => (model.value = [...next])

function toggleCell(row, a) {
    if (props.readonly) return
    const next = new Set(granted.value)
    const n = keyOf(row, a)
    if (next.has(n)) {
        next.delete(n)
        if (a === 'view') rowNames(row).forEach(x => next.delete(x))
    } else {
        next.add(n)
        if (a !== 'view' && row.actions.includes('view')) next.add(keyOf(row, 'view'))
    }
    commit(next)
}

function setMany(names, on) {
    if (props.readonly) return
    const next = new Set(granted.value)
    names.forEach(n => (on ? next.add(n) : next.delete(n)))
    commit(next)
}
const toggleRow = row => setMany(rowNames(row), countIn(rowNames(row)) !== row.actions.length)
const toggleGroup = g => { const names = groupNames(g); setMany(names, countIn(names) !== names.length) }
</script>

<template>
    <div class="flex flex-col gap-3">
        <input v-model="q" type="search" placeholder="Search permissions..." aria-label="Search permissions"
            class="field w-64" />

        <p v-if="!visibleGroups.length" class="py-4 text-center text-muted">No matching permissions</p>

        <div v-for="g in visibleGroups" :key="g.key" class="overflow-hidden rounded-md border border-line">
            <div class="flex items-center justify-between bg-bg/70 px-3 py-2">
                <label class="flex items-center gap-2 font-semibold">
                    <input type="checkbox" class="size-3.5 accent-accent"
                        :aria-label="`Select all ${g.label} permissions`"
                        :checked="countIn(groupNames(g)) === groupNames(g).length"
                        :indeterminate="countIn(groupNames(g)) > 0 && countIn(groupNames(g)) < groupNames(g).length"
                        :disabled="readonly" @change="toggleGroup(g)" />
                    {{ g.label }}
                </label>
                <span class="text-[11px] text-muted">{{ countIn(groupNames(g)) }} / {{ groupNames(g).length }}</span>
            </div>
            <table class="w-full text-[12px]">
                <thead>
                    <tr class="border-b border-line text-[11px] text-muted">
                        <th class="px-3 py-1.5 text-left font-medium">Doctype</th>
                        <th v-for="c in g.columns" :key="c" class="w-24 px-2 text-center font-medium">{{ cap(c) }}</th>
                        <th class="w-16 px-2 text-center font-medium">All</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in visibleRows(g)" :key="r.key"
                        class="border-b border-line/60 last:border-b-0 hover:bg-accent-soft/40">
                        <th scope="row" class="px-3 py-1.5 text-left font-medium">{{ r.label }}</th>
                        <td v-for="c in g.columns" :key="c" class="text-center">
                            <input v-if="r.actions.includes(c)" type="checkbox" class="size-3.5 accent-accent"
                                :aria-label="`${r.label}: ${c}`" :checked="granted.has(keyOf(r, c))"
                                :disabled="readonly" @change="toggleCell(r, c)" />
                            <span v-else class="text-muted/50" aria-hidden="true">-</span>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="size-3.5 accent-accent" :aria-label="`${r.label}: all`"
                                :checked="countIn(rowNames(r)) === r.actions.length"
                                :indeterminate="countIn(rowNames(r)) > 0 && countIn(rowNames(r)) < r.actions.length"
                                :disabled="readonly" @change="toggleRow(r)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>