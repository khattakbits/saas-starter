<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import Checkbox from '@core/ui/Checkbox.vue'
import SideSection from '@core/ui/SideSection.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import ConfirmDialog from '@core/ui/ConfirmDialog.vue'
import Icon from '@core/ui/Icon.vue'
import { useUnsavedGuard } from '@core/composables/useUnsavedGuard'

defineOptions({ layout: AppShell })
const props = defineProps({ template: Object, canEdit: Boolean })

const form = useForm({
    subject: props.template.subject,
    body: props.template.body,
    is_active: props.template.is_active,
})
useUnsavedGuard(() => form.isDirty)

const base = `/settings/email-templates/${props.template.key}`
const tokenOf = name => `{{ ${name} }}`

/* ---------- placeholder insert at cursor ---------- */
const subjectRef = ref(null)
const bodyRef = ref(null)
const focused = ref('body')

const elFor = field => (field === 'subject' ? subjectRef.value?.$el : bodyRef.value) ?? null

async function insertToken(name) {
    const field = focused.value
    const el = elFor(field)
    const value = form[field] ?? ''
    const token = tokenOf(name)

    if (!el) {
        form[field] = value + token
        return
    }

    const start = el.selectionStart ?? value.length
    const end = el.selectionEnd ?? start
    form[field] = value.slice(0, start) + token + value.slice(end)

    await nextTick()
    el.focus()
    el.setSelectionRange(start + token.length, start + token.length)
}

/* ---------- preview ---------- */
const preview = ref(null)
const previewError = ref('')
const previewing = ref(false)

const xsrf = () => {
    const c = document.cookie.split('; ').find(x => x.startsWith('XSRF-TOKEN='))
    return c ? decodeURIComponent(c.slice('XSRF-TOKEN='.length)) : ''
}

async function loadPreview() {
    previewing.value = true
    previewError.value = ''
    try {
        const res = await fetch(`${base}/preview`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ subject: form.subject, body: form.body }),
        })
        if (res.status === 401 || res.status === 419) {
            window.location.href = '/login'
            return
        }
        if (!res.ok) throw new Error()
        preview.value = await res.json()
    } catch {
        previewError.value = 'Could not build the preview.'
    }
    previewing.value = false
}

onMounted(loadPreview)

/* ---------- actions ---------- */
function save() {
    form.put(base, { preserveScroll: true, onSuccess: () => form.defaults() })
}

function sendTest() {
    router.post(`${base}/test`, { subject: form.subject, body: form.body }, {
        preserveScroll: true,
        preserveState: true,
    })
}

const resetOpen = ref(false)
const resetting = ref(false)

function resetTemplate() {
    resetting.value = true
    // Remount hone se form khud defaults se bhar jata hai
    router.delete(base, {
        preserveScroll: true,
        onFinish: () => { resetting.value = false; resetOpen.value = false },
    })
}

const state = computed(() => (props.template.customized ? 'Customized' : 'Default'))
</script>

<template>
    <FormPage :title="template.name" :description="template.key" back="/settings/email-templates"
        back-label="Email Templates">
        <template #badge>
            <StatusBadge :tone="template.customized ? 'info' : 'neutral'">{{ state }}</StatusBadge>
            <StatusBadge v-if="!template.is_active && template.customized" tone="neutral">Off</StatusBadge>
            <StatusBadge v-if="form.isDirty" tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button v-if="canEdit" type="button" class="btn" @click="sendTest">
                <Icon name="send" :size="13" />Send test to me
            </button>
            <button v-if="canEdit && template.customized" type="button" class="btn btn-danger"
                @click="resetOpen = true">Reset to default</button>
            <button v-if="form.isDirty" type="button" class="btn" @click="form.reset()">Discard</button>
            <button v-if="canEdit && form.isDirty" type="submit" form="email-template-form" class="btn btn-primary"
                :disabled="form.processing">Save</button>
        </template>

        <template #side>
            <SideSection title="Placeholders" icon="tag">
                <p class="mb-2 text-[11px] text-muted">Click one to insert it where the cursor is.</p>
                <ul class="flex flex-col gap-0.5">
                    <li v-for="p in template.placeholders" :key="p.name">
                        <button type="button"
                            class="w-full rounded-md px-1.5 py-1 text-left outline-none hover:bg-bg focus-visible:ring-2 focus-visible:ring-accent"
                            @click="insertToken(p.name)">
                            <span class="font-mono text-[12px] font-medium text-accent-dark">{{ tokenOf(p.name) }}</span>
                            <span v-if="p.required" class="ml-1 text-[11px] font-semibold text-red-700">required</span>
                            <span class="block text-[11px] leading-4 text-muted">{{ p.description }}</span>
                        </button>
                    </li>
                </ul>
            </SideSection>
        </template>

        <form id="email-template-form" @submit.prevent="save">
            <FormCard>
                <FormSection title="Template" icon="mail">
                    <FormGrid :cols="1">
                        <Field label="Subject" required :error="form.errors.subject" v-slot="{ id, invalid }">
                            <Input :id="id" ref="subjectRef" v-model="form.subject" autocomplete="off"
                                :disabled="!canEdit" :invalid="invalid" @focus="focused = 'subject'" />
                        </Field>
                        <Field label="Body" required hint="Plain text. Blank line = new paragraph."
                            :error="form.errors.body" v-slot="{ id, invalid }">
                            <textarea :id="id" ref="bodyRef" v-model="form.body" rows="14"
                                class="field h-auto resize-y py-1.5 font-mono" :disabled="!canEdit"
                                :aria-invalid="invalid || undefined"
                                :aria-describedby="invalid ? id + '-err' : undefined"
                                @focus="focused = 'body'"></textarea>
                        </Field>
                    </FormGrid>
                    <div class="mt-4">
                        <Checkbox v-model="form.is_active" label="Active (use this text instead of the default)"
                            :disabled="!canEdit" />
                    </div>
                </FormSection>

                <FormSection title="Preview" icon="eye">
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn" :disabled="previewing" @click="loadPreview">
                            {{ previewing ? 'Building...' : 'Refresh preview' }}
                        </button>
                        <span class="text-[11px] text-muted">Rendered with sample data, using the text above.</span>
                    </div>

                    <p v-if="previewError" role="alert" class="mt-2 text-[12px] text-red-700">{{ previewError }}</p>

                    <div v-else-if="preview" class="mt-2 rounded-md border border-line bg-bg px-3 py-2">
                        <p class="text-[13px] font-semibold">{{ preview.subject }}</p>
                        <pre
                            class="mt-1 whitespace-pre-wrap break-words font-sans text-[12px] leading-5 text-ink/80">{{ preview.text }}</pre>
                    </div>
                </FormSection>
            </FormCard>
        </form>

        <ConfirmDialog v-model="resetOpen" title="Reset to the default?" confirm-label="Reset" danger
            :disabled="resetting" @confirm="resetTemplate">
            <p>The custom text of <strong>{{ template.name }}</strong> will be deleted and the built-in default used
                again.</p>
        </ConfirmDialog>
    </FormPage>
</template>
