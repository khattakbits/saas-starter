<script setup>
import { computed, shallowRef } from 'vue'

const props = defineProps({
    columns: { type: Array, required: true },   // { key, label, width, align, type: 'text' | 'number', readonly }
    newRow: { type: Function, required: true },
    addLabel: { type: String, default: 'Add Row' },
})
const rows = defineModel({ type: Array, default: () => [] })

const selected = shallowRef(new Set())
const gridCols = computed(() => `32px 40px ${props.columns.map(c => c.width ?? '1fr').join(' ')}`)

let uid = 0
const ids = new WeakMap()
const keyOf = row => (ids.has(row) ? ids.get(row) : (ids.set(row, ++uid), uid))

const allSelected = computed(() => rows.value.length > 0 && rows.value.every(r => selected.value.has(keyOf(r))))

function toggle(row) {
    const next = new Set(selected.value)
    const k = keyOf(row)
    next.has(k) ? next.delete(k) : next.add(k)
    selected.value = next
}
const toggleAll = () => (selected.value = allSelected.value ? new Set() : new Set(rows.value.map(keyOf)))
const add = () => (rows.value = [...rows.value, props.newRow()])
function removeSelected() {
    rows.value = rows.value.filter(r => !selected.value.has(keyOf(r)))
    selected.value = new Set()
}
function set(row, c, e) {
    row[c.key] = c.type === 'number' ? (e.target.valueAsNumber || 0) : e.target.value
}
</script>

<template>
    <div>
        <div class="overflow-hidden rounded-md border border-line">
            <div class="grid h-8 items-stretch border-b border-line bg-bg text-[11px] font-semibold text-muted"
                :style="{ gridTemplateColumns: gridCols }">
                <span class="flex items-center justify-center border-r border-line">
                    <input type="checkbox" class="size-3 accent-accent" aria-label="Select all rows"
                        :checked="allSelected" :disabled="!rows.length" @change="toggleAll" />
                </span>
                <span class="flex items-center justify-center border-r border-line">No.</span>
                <span v-for="c in columns" :key="c.key"
                    class="flex items-center border-r border-line px-2 last:border-r-0"
                    :class="c.align === 'right' ? 'justify-end' : ''">{{ c.label }}</span>
            </div>

            <div v-for="(row, i) in rows" :key="keyOf(row)"
                class="grid min-h-8 items-stretch border-b border-line/70 last:border-b-0"
                :style="{ gridTemplateColumns: gridCols }">
                <span class="flex items-center justify-center border-r border-line/70">
                    <input type="checkbox" class="size-3 accent-accent" :aria-label="`Select row ${i + 1}`"
                        :checked="selected.has(keyOf(row))" @change="toggle(row)" />
                </span>
                <span class="flex items-center justify-center border-r border-line/70 text-muted">{{ i + 1 }}</span>
                <span v-for="c in columns" :key="c.key"
                    class="flex items-center border-r border-line/70 last:border-r-0">
                    <slot :name="`cell-${c.key}`" :row="row" :index="i">
                        <input :type="c.type === 'number' ? 'number' : 'text'" :value="row[c.key]"
                            :readonly="c.readonly" :aria-label="`${c.label}, row ${i + 1}`"
                            class="h-8 w-full bg-transparent px-2 outline-none focus:bg-accent-soft/50"
                            :class="c.align === 'right' ? 'text-right tabular-nums' : ''"
                            @input="set(row, c, $event)" />
                    </slot>
                </span>
            </div>

            <p v-if="!rows.length" class="py-5 text-center text-muted">No rows yet</p>
        </div>

        <div class="mt-2 flex items-center gap-2">
            <button type="button" class="btn" @click="add">{{ addLabel }}</button>
            <button v-if="selected.size" type="button" class="btn" @click="removeSelected">Delete {{ selected.size
                }}</button>
        </div>
    </div>
</template>