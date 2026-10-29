<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
    type: { type: String, required: true },          // config/attachments.php ka type
    id: { type: [Number, String], required: true },  // record ki id
    readonly: Boolean,
    maxMb: { type: Number, default: 10 },
})

const ALLOWED = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xls', 'xlsx', 'csv', 'mp3', 'm4a', 'wav', 'ogg', 'oga', 'opus', 'aac', 'weba', 'webm']
const ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx,.csv,audio/*'
const MAX_SECONDS = 300

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
    if (!res.ok) error.value = 'Could not load attachments.'
    loading.value = false
}

async function upload(list) {
    error.value = ''
    for (const file of list) {
        const ext = file.name.split('.').pop().toLowerCase()
        if (!ALLOWED.includes(ext)) {
            error.value = `${file.name}: only PDF, images, Excel, CSV and voice recordings can be attached.`
            continue
        }
        if (file.size > props.maxMb * 1048576) {
            error.value = `${file.name}: files larger than ${props.maxMb} MB cannot be attached.`
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
            error.value = j.errors?.file?.[0] ?? j.message ?? 'Upload failed.'
        }
    }
}

const onPick = e => { upload([...e.target.files]); e.target.value = '' }
const onDrop = e => { dragging.value = false; if (!props.readonly) upload([...e.dataTransfer.files]) }

async function remove(f) {
    if (confirmId.value !== f.id) { confirmId.value = f.id; return }   // pehli click: tasdeeq, doosri: delete
    const res = await api(`/attachments/${f.id}`, { method: 'DELETE' })
    if (res.ok) files.value = files.value.filter(x => x.id !== f.id)
    else error.value = 'Could not remove the file.'
    confirmId.value = null
}

/* ---------- voice recording ---------- */
const canRecord = ref(false)
const recording = ref(false)
const seconds = ref(0)
let recorder, stream, chunks = [], tick, saveOnStop = true

const clock = () => `${String(Math.floor(seconds.value / 60)).padStart(2, '0')}:${String(seconds.value % 60).padStart(2, '0')}`

async function startRecording() {
    error.value = ''
    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    } catch {
        error.value = 'Microphone access is blocked. Allow it from the address bar and try again.'
        return
    }
    const mime = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']
        .find(t => MediaRecorder.isTypeSupported(t))
    recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined)
    chunks = []
    saveOnStop = true
    recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data) }
    recorder.onstop = () => {
        stream?.getTracks().forEach(t => t.stop())
        clearInterval(tick)
        recording.value = false
        if (!saveOnStop || !chunks.length) return
        const type = recorder.mimeType || 'audio/webm'
        const ext = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm'
        const d = new Date()
        const p = n => String(n).padStart(2, '0')
        const name = `voice-${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}.${ext}`
        upload([new File(chunks, name, { type })])
    }
    recorder.start()
    seconds.value = 0
    recording.value = true
    tick = setInterval(() => { seconds.value++; if (seconds.value >= MAX_SECONDS) stopRecording() }, 1000)
}

function stopRecording(save = true) {
    saveOnStop = save
    if (recorder && recorder.state !== 'inactive') recorder.stop()
}

onMounted(() => {
    canRecord.value = !!navigator.mediaDevices?.getUserMedia && typeof MediaRecorder !== 'undefined'
    load()
})
onBeforeUnmount(() => {
    clearInterval(tick)
    saveOnStop = false
    if (recorder && recorder.state !== 'inactive') recorder.stop()
    stream?.getTracks().forEach(t => t.stop())
})
</script>

<template>
    <div @dragover.prevent="!readonly && (dragging = true)" @dragleave="dragging = false" @drop.prevent="onDrop">
        <div v-if="!readonly" class="flex flex-col gap-2">
            <div v-if="recording"
                class="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-2.5 py-1.5 text-red-800"
                role="status">
                <span class="size-2 animate-pulse rounded-full bg-red-600" aria-hidden="true"></span>
                <span class="flex-1 font-medium tabular-nums">Recording {{ clock() }}</span>
                <button type="button" class="btn btn-primary" @click="stopRecording()">Attach</button>
                <button type="button" class="btn" @click="stopRecording(false)">Cancel</button>
            </div>
            <template v-else>
                <button type="button" :disabled="busy"
                    class="flex h-9 w-full items-center justify-between rounded-md border border-dashed px-3 text-[13px] text-ink/80 hover:bg-bg"
                    :class="dragging ? 'border-accent bg-accent-soft' : 'border-line'" @click="input.click()">
                    <span>{{ busy ? 'Uploading...' : dragging ? 'Drop to upload' : 'Attach file' }}</span>
                    <Icon name="plus" :size="15" />
                </button>
                <button v-if="canRecord" type="button" class="btn w-full" :disabled="busy" @click="startRecording">
                    <Icon name="mic" :size="16" />Record voice
                </button>
            </template>
            <p class="text-[11px] text-muted">PDF, images, Excel, CSV or voice. Max {{ maxMb }} MB.</p>
        </div>
        <input ref="input" type="file" multiple :accept="ACCEPT" class="sr-only" tabindex="-1"
            aria-label="Choose files to attach" @change="onPick" />

        <p v-if="error" role="alert" class="mt-1.5 text-[12px] text-red-700">{{ error }}</p>

        <ul class="mt-2 flex flex-col gap-1" aria-live="polite">
            <li v-for="f in files" :key="f.id"
                class="flex items-center gap-1.5 rounded-md border border-line bg-surface px-2 py-1.5">
                <Icon name="file" :size="15" class="text-muted" />
                <a :href="`/attachments/${f.id}`" class="min-w-0 flex-1 truncate text-accent-dark hover:underline"
                    :title="f.name">{{ f.name }}</a>
                <span class="flex-none text-[11px] text-muted">{{ size(f.size) }}</span>
                <button v-if="!readonly" type="button"
                    class="flex h-6 flex-none items-center rounded px-1 text-muted hover:bg-bg hover:text-red-700"
                    :aria-label="confirmId === f.id ? `Confirm delete ${f.name}` : `Delete ${f.name}`"
                    @click="remove(f)" @blur="confirmId = null">
                    <span v-if="confirmId === f.id" class="text-[11px] font-semibold text-red-700">Delete?</span>
                    <Icon v-else name="x" :size="14" />
                </button>
            </li>
        </ul>
        <p v-if="!loading && !files.length" class="mt-2 text-muted">No attachments</p>
    </div>
</template>