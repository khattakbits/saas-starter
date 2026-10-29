<script setup>
import { computed, onMounted, ref, useId, watch } from 'vue'
import Icon from './Icon.vue'
import { useFormat } from '@core/composables/useFormat'

const props = defineProps({
    type: { type: String, required: true },          // doc type key, jaise "demo_entry"
    id: { type: [Number, String], required: true },
    reloadKey: { type: [String, Number], default: '' },   // lifecycle action ke baad badalta hai
})

const { ago, dateTime } = useFormat()
const whoId = useId()

const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')
const views = ref({ total: 0, people: 0, viewers: [] })
const timeline = ref([])
const nextBefore = ref(null)
const visible = ref(8)
const whoOpen = ref(false)

const xsrf = () => {
    const c = document.cookie.split('; ').find(x => x.startsWith('XSRF-TOKEN='))
    return c ? decodeURIComponent(c.slice('XSRF-TOKEN='.length)) : ''
}

async function api(url, opts = {}) {
    const res = await fetch(url, { ...opts, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), ...(opts.headers ?? {}) } })
    if (res.status === 401 || res.status === 419) {
        window.location.href = '/login'
        return new Promise(() => { })
    }
    return res
}

const base = () => `/documents/${encodeURIComponent(props.type)}/${props.id}`

// View sirf mount par ginta hai: link hover/prefetch se counter nahi barhta
async function recordView() {
    try {
        await api(`${base()}/views`, { method: 'POST' })
    } catch {
        // ginti na ho to bhi panel chalta rahe
    }
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const res = await api(`${base()}/activity`)
        if (!res.ok) throw new Error()
        const data = await res.json()
        views.value = data.views ?? { total: 0, people: 0, viewers: [] }
        timeline.value = data.timeline ?? []
        nextBefore.value = data.next_before ?? null
        visible.value = 8
    } catch {
        error.value = 'Could not load activity.'
    }
    loading.value = false
}

async function more() {
    if (loadingMore.value) return
    if (visible.value >= timeline.value.length && nextBefore.value) {
        loadingMore.value = true
        try {
            const res = await api(`${base()}/activity?before=${nextBefore.value}`)
            if (res.ok) {
                const data = await res.json()
                timeline.value = [...timeline.value, ...(data.timeline ?? [])]
                nextBefore.value = data.next_before ?? null
            }
        } catch {
            // purani entries na aayen to jo dikh rahi hain wo rehne do
        }
        loadingMore.value = false
    }
    visible.value += 8
}

const shown = computed(() => timeline.value.slice(0, visible.value))
const hasMore = computed(() => visible.value < timeline.value.length || !!nextBefore.value)

const viewsText = computed(() => {
    const { total, people } = views.value
    if (!total) return 'No views yet'
    return `Viewed ${total} ${total === 1 ? 'time' : 'times'} by ${people} ${people === 1 ? 'person' : 'people'}`
})

onMounted(async () => {
    await recordView()   // pehle view, phir taza numbers
    await load()
})

// Submit/Cancel/Save ke baad wahi page dobara render hota hai: sirf refresh, naya view nahi
watch(() => `${props.type}:${props.id}:${props.reloadKey}`, () => load())
</script>

<template>
    <!-- Live region hamesha mojood rehta hai, is liye load hone par badla hua content announce hota hai -->
    <div class="min-w-0" aria-live="polite">
        <div v-if="loading" class="flex flex-col gap-2" aria-hidden="true">
            <div class="h-3 w-3/4 animate-pulse rounded bg-line"></div>
            <div class="h-3 w-1/2 animate-pulse rounded bg-line"></div>
            <div class="h-3 w-2/3 animate-pulse rounded bg-line"></div>
        </div>

        <div v-else-if="error" role="alert" class="flex flex-col items-start gap-1.5">
            <p class="text-[13px] text-red-700">{{ error }}</p>
            <button type="button" class="btn" @click="load">Retry</button>
        </div>

        <div v-else class="min-w-0">
            <div class="flex items-start gap-1.5">
                <Icon name="eye" :size="13" class="mt-0.5 flex-none text-muted" />
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] text-ink/90">{{ viewsText }}</p>
                    <button v-if="views.viewers.length" type="button"
                        class="mt-1 flex items-center gap-1 text-[11px] font-medium text-accent-dark hover:underline"
                        :aria-expanded="whoOpen" :aria-controls="whoId" @click="whoOpen = !whoOpen">
                        <Icon name="chevron-down" :size="11" class="transition-transform"
                            :class="whoOpen ? 'rotate-180' : ''" />
                        Who viewed
                    </button>
                    <ul v-show="whoOpen" :id="whoId" class="mt-1.5 flex flex-col gap-1">
                        <li v-for="v in views.viewers" :key="v.user_id ?? v.name"
                            class="flex flex-wrap items-baseline justify-between gap-x-2 text-[11px] leading-4">
                            <span class="min-w-0 truncate text-ink/80">{{ v.name || 'System' }}</span>
                            <span class="flex-none text-muted">{{ v.views }} {{ v.views === 1 ? 'view' : 'views' }}
                                · {{ ago(v.last_viewed_at) }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <p v-if="!timeline.length" class="mt-3 text-[13px] text-muted">No activity yet</p>

            <ol v-else class="mt-3 flex flex-col border-l border-line">
                <li v-for="e in shown" :key="e.id" class="relative min-w-0 pb-3 pl-3.5 last:pb-0">
                    <span class="absolute left-0 top-1.5 size-1.5 -translate-x-1/2 rounded-full bg-muted/60"
                        aria-hidden="true"></span>
                    <p class="text-[13px] font-semibold leading-5 text-ink">{{ e.title }}</p>
                    <p class="text-[11px] leading-4 text-muted">{{ e.user_name ? `by ${e.user_name}` : 'System' }}</p>
                    <p v-if="e.details?.length" class="break-words text-[11px] leading-4 text-muted">
                        Changed: {{ e.details.join(', ') }}
                    </p>
                    <p class="flex flex-wrap items-baseline gap-x-1 text-[11px] leading-4 text-muted">
                        <time :datetime="e.at" :title="dateTime(e.at, true)">{{ ago(e.at) }}</time>
                        <span aria-hidden="true">·</span>
                        <span>{{ dateTime(e.at, true) }}</span>
                    </p>
                </li>
            </ol>

            <button v-if="hasMore" type="button" class="btn mt-2 w-full" :disabled="loadingMore" @click="more">
                {{ loadingMore ? 'Loading...' : 'Show more' }}
            </button>
        </div>
    </div>
</template>
