<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useFloating } from '@core/composables/useFloating'
import Icon from './Icon.vue'

const props = defineProps({
    id: String,
    placeholder: { type: String, default: 'DD-MM-YYYY' },
    invalid: Boolean,
    disabled: Boolean,
    weekStart: { type: Number, default: 0 },   // 0 = Sunday, 1 = Monday
})
const model = defineModel()   // 'YYYY-MM-DD' ya ''

const pad = n => String(n).padStart(2, '0')
const toIso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`   // local date, UTC nahi
const fromIso = s => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s ?? '')
    return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null
}
const show = iso => {
    const d = fromIso(iso)
    return d ? `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()}` : ''
}
const startOfMonth = d => new Date(d.getFullYear(), d.getMonth(), 1)

function parse(raw) {
    const t = raw.trim()
    const m = /^(\d{1,2})[-/. ](\d{1,2})[-/. ](\d{4})$/.exec(t) || /^(\d{2})(\d{2})(\d{4})$/.exec(t)
    if (!m) return null
    const d = +m[1], mo = +m[2], y = +m[3]
    const dt = new Date(y, mo - 1, d)
    return dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d ? dt : null
}

const anchor = ref(null)
const input = ref(null)
const open = ref(false)
const text = ref(show(model.value))
const view = ref(startOfMonth(fromIso(model.value) ?? new Date()))
const { style: floatStyle, place, start, stop } = useFloating(input, { width: 264, maxHeight: 420 })

watch(model, v => { text.value = show(v) })

async function openPicker() {
    if (props.disabled || open.value) return
    view.value = startOfMonth(fromIso(model.value) ?? new Date())
    open.value = true
    await nextTick()
    place(340)
    start()
}

function closePicker() {
    open.value = false
    stop()
}

function commit() {
    const raw = text.value.trim()
    if (!raw) { model.value = ''; return }
    const dt = parse(raw)
    if (dt) model.value = toIso(dt)
    text.value = show(model.value)     // galat likha ho to pichli sahi date wapas
}

function onInput() {
    const dt = parse(text.value)
    if (dt) view.value = startOfMonth(dt)
}

function pick(iso) {
    model.value = iso
    text.value = show(iso)
    closePicker()
}

const shift = n => (view.value = new Date(view.value.getFullYear(), view.value.getMonth() + n, 1))

function onFocus() {
    input.value?.select()
    openPicker()
}

function onBlur() {
    commit()
    closePicker()
}

function onKey(e) {
    if (e.key === 'Escape' && open.value) {
        e.preventDefault()
        e.stopPropagation()      // modal band na ho, sirf calendar
        closePicker()
        text.value = show(model.value)
    } else if (e.key === 'Enter' && open.value) {
        e.preventDefault()       // pehle date commit, form submit nahi
        commit()
        closePicker()
    } else if (e.key === 'ArrowDown') {
        e.preventDefault()
        openPicker()
    } else if ((e.key === 'PageUp' || e.key === 'PageDown') && open.value) {
        e.preventDefault()
        shift((e.key === 'PageUp' ? -1 : 1) * (e.shiftKey ? 12 : 1))
    }
}

const cells = computed(() => {
    const y = view.value.getFullYear()
    const m = view.value.getMonth()
    const offset = (new Date(y, m, 1).getDay() - props.weekStart + 7) % 7
    const selected = model.value
    const today = toIso(new Date())
    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(y, m, 1 - offset + i)
        const iso = toIso(d)
        return { iso, day: d.getDate(), out: d.getMonth() !== m, today: iso === today, selected: iso === selected }
    })
})

const weekdays = computed(() => {
    const base = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA']
    return [...base.slice(props.weekStart), ...base.slice(0, props.weekStart)]
})
const monthName = computed(() => new Intl.DateTimeFormat('en', { month: 'long' }).format(view.value))
const year = computed(() => view.value.getFullYear())
</script>

<template>
    <div ref="anchor" class="relative">
        <input :id="id" ref="input" v-model="text" type="text" inputmode="numeric" autocomplete="off" class="field"
            :placeholder="placeholder" :disabled="disabled" aria-haspopup="dialog" :aria-expanded="open"
            :aria-invalid="invalid || undefined" :aria-describedby="invalid && id ? id + '-err' : undefined"
            @focus="onFocus" @click="openPicker" @blur="onBlur" @input="onInput" @keydown="onKey" />

        <div v-show="open" role="dialog" aria-label="Choose date" :style="floatStyle"
            class="fixed z-50 rounded-lg border border-line bg-surface p-3 shadow-lg" @mousedown.prevent>
            <div class="mb-2 flex items-center justify-between">
                <button type="button"
                    class="flex size-7 items-center justify-center rounded-md text-muted hover:bg-bg hover:text-ink"
                    aria-label="Previous month" @click="shift(-1)">
                    <Icon name="chevron-left" :size="14" />
                </button>
                <div class="text-[12px] font-medium">{{ monthName }}<span class="text-muted">, {{ year }}</span></div>
                <button type="button"
                    class="flex size-7 items-center justify-center rounded-md text-muted hover:bg-bg hover:text-ink"
                    aria-label="Next month" @click="shift(1)">
                    <Icon name="chevron-right" :size="14" />
                </button>
            </div>

            <div class="grid grid-cols-7 border-b border-line pb-1.5 text-center text-[10px] font-semibold text-ink/70">
                <span v-for="d in weekdays" :key="d">{{ d }}</span>
            </div>
            <div class="grid grid-cols-7 gap-y-0.5 pt-1.5">
                <button v-for="c in cells" :key="c.iso" type="button" :aria-label="show(c.iso)"
                    :aria-pressed="c.selected"
                    class="mx-auto flex size-8 items-center justify-center rounded-md text-[12px]" :class="c.selected ? 'bg-accent font-semibold text-white'
                        : c.today ? 'font-semibold text-accent-dark hover:bg-accent-soft'
                            : c.out ? 'text-ink/35 hover:bg-bg' : 'hover:bg-accent-soft'" @click="pick(c.iso)">{{ c.day
                            }}</button>
            </div>

            <div class="mt-2 border-t border-line pt-2 text-center">
                <button type="button" class="text-[12px] font-medium text-accent-dark hover:underline"
                    @click="pick(toIso(new Date()))">Today</button>
            </div>
        </div>
    </div>
</template>