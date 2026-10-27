<script setup>
import { onMounted, ref } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
    type: { type: String, required: true },          // config/attachments.php ka type
    id: { type: [Number, String], required: true },  // record ki id
    readonly: Boolean,
    maxMb: { type: Number, default: 10 },
})

const files = ref([])
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const dragging = ref(false)
const confirmId = ref(null)
const input = ref(null)

const xsrf = () => {
    const c = document.cookie.split('; ').find(x => x.startsWith('XSRF-TOKEN='))
    return c ? decodeURIComponent(c.slice('XSRF-TOKEN='.length)) : ''
}
const size = n => (n < 1048576 ? `${Math.max(1, Math.round(n / 1024))} KB` : `${(n / 1048576).toFixed(1)} MB`)

async function api(url, opts = {}) {
    const res = await fetch(url, { ...opts, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), ...(opts.headers ?? {}) } })
    if (res.status === 401 || res.status === 419) {
        window.location.href = '/login'
        return new Promise(() => { })
    }
    return res
}

async function load() {
    loading.value = true
    const res = await api(`/attachments?type=${encodeURIComponent(props.type)}&id=${props.id}`)
    files.value = res.ok ? (await res.json()).data : []
    if (!res.ok) error.value = 'Attachments load nahi hui.'
    loading.value = false
}

async function upload(list) {
    error.value = ''
    for (const file of list) {
        if (file.size > props.maxMb * 1048576) {
            error.value = `${file.name}: ${props.maxMb} MB se bari file nahi lag sakti.`
            continue
        }
        const body = new FormData()
        body.append('type', props.type)
        body.append('id', props.id)
        body.append('file', file)
        busy.value = true
        const res = await api('/attachments', { method: 'POST', body })
        busy.value = false
        if (res.ok) files.value.unshift((await res.json()).data)
        else {
            const j = await res.json().catch(() => ({}))
            error.value = j.errors?.file?.[0] ?? j.message ?? 'Upload nahi hui.'
        }
    }
}

const onPick = e => { upload([...e.target.files]); e.target.value = '' }
const onDrop = e => { dragging.value = false; if (!props.readonly) upload([...e.dataTransfer.files]) }

async function remove(f) {
    if (confirmId.value !== f.id) { confirmId.value = f.id; return }   // pehli click: tasdeeq, doosri: delete
    const res = await api(`/attachments/${f.id}`, { method: 'DELETE' })
    if (res.ok) files.value = files.value.filter(x => x.id !== f.id)
    else error.value = 'File hata nahi saki.'
    confirmId.value = null
}

onMounted(load)
</script>

<template>
    <div @dragover.prevent="!readonly && (dragging = true)" @dragleave="dragging = false" @drop.prevent="onDrop">
        <button v-if="!readonly" type="button" :disabled="busy"
            class="flex h-8 w-full items-center justify-between rounded-md border border-dashed px-2.5 text-ink/80 hover:bg-bg"
            :class="dragging ? 'border-accent bg-accent-soft' : 'border-line'" @click="input.click()">
            <span>{{ busy ? 'Uploading...' : dragging ? 'Drop to upload' : 'Attach file' }}</span>
            <Icon name="plus" :size="14" />
        </button>
        <input ref="input" type="file" multiple class="sr-only" tabindex="-1" aria-label="Choose files to attach"
            @change="onPick" />

        <p v-if="error" role="alert" class="mt-1.5 text-[11px] text-red-700">{{ error }}</p>

        <ul class="mt-2 flex flex-col gap-1" aria-live="polite">
            <li v-for="f in files" :key="f.id"
                class="flex items-center gap-1.5 rounded-md border border-line bg-surface px-2 py-1.5">
                <Icon name="file" :size="14" class="text-muted" />
                <a :href="`/attachments/${f.id}`" class="min-w-0 flex-1 truncate text-accent-dark hover:underline"
                    :title="f.name">{{ f.name }}</a>
                <span class="flex-none text-[10px] text-muted">{{ size(f.size) }}</span>
                <button v-if="!readonly" type="button"
                    class="flex h-5 flex-none items-center rounded px-1 text-muted hover:bg-bg hover:text-red-700"
                    :aria-label="confirmId === f.id ? `Confirm delete ${f.name}` : `Delete ${f.name}`"
                    @click="remove(f)" @blur="confirmId = null">
                    <span v-if="confirmId === f.id" class="text-[10px] font-semibold text-red-700">Delete?</span>
                    <Icon v-else name="x" :size="12" />
                </button>
            </li>
        </ul>
        <p v-if="!loading && !files.length" class="mt-2 text-muted">No attachments</p>
    </div>
</template>