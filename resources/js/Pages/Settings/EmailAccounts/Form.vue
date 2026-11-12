<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import Select from '@core/ui/Select.vue'
import Checkbox from '@core/ui/Checkbox.vue'
import StatusBadge from '@core/ui/StatusBadge.vue'
import Icon from '@core/ui/Icon.vue'
import { useFormat } from '@core/composables/useFormat'
import { useUnsavedGuard } from '@core/composables/useUnsavedGuard'

defineOptions({ layout: AppShell })
const props = defineProps({ account: Object, testTo: String })
const { dateTime } = useFormat()

const editing = computed(() => !!props.account)

const form = useForm({
    name: props.account?.name ?? '',
    from_email: props.account?.from_email ?? '',
    from_name: props.account?.from_name ?? '',
    host: props.account?.host ?? '',
    port: props.account?.port ?? '587',
    encryption: props.account?.encryption ?? 'tls',
    username: props.account?.username ?? '',
    password: '',
    is_active: props.account?.is_active ?? true,
    is_default: props.account?.is_default ?? false,
})
useUnsavedGuard(() => form.isDirty)

const encryptions = [
    { value: 'none', label: 'None' },
    { value: 'tls', label: 'TLS (STARTTLS)' },
    { value: 'ssl', label: 'SSL (implicit)' },
]

function save() {
    const done = { preserveScroll: true, onSuccess: () => { form.reset('password'); form.defaults() } }
    editing.value ? form.put(`/settings/email-accounts/${props.account.id}`, done) : form.post('/settings/email-accounts', done)
}

/* ---------- test email (E1 route, synchronous) ---------- */
const to = ref(props.testTo ?? '')
const testing = ref(false)
const result = ref(null)
const tested = ref(null)   // { ok, at } — server prop ko reload kiye bagair taza last-test

const lastTest = computed(() => {
    if (tested.value) {
        return `${tested.value.ok ? 'OK' : 'Failed'} · ${dateTime(tested.value.at, true)}`
    }
    if (!props.account) return ''
    if (props.account.last_test_ok === null || !props.account.last_tested_at) return 'Never tested'
    return `${props.account.last_test_ok ? 'OK' : 'Failed'} · ${dateTime(props.account.last_tested_at, true)}`
})

const xsrf = () => {
    const c = document.cookie.split('; ').find(x => x.startsWith('XSRF-TOKEN='))
    return c ? decodeURIComponent(c.slice('XSRF-TOKEN='.length)) : ''
}

async function sendTest() {
    testing.value = true
    result.value = null
    try {
        const res = await fetch(`/settings/email-accounts/${props.account.id}/test`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ to: to.value }),
        })
        if (res.status === 401 || res.status === 419) {
            window.location.href = '/login'
            return
        }
        if (res.status === 422) {
            const j = await res.json()
            result.value = { ok: false, error: j.errors?.to?.[0] ?? 'Enter a valid email address.' }
        } else if (res.ok) {
            result.value = await res.json()
        } else {
            result.value = { ok: false, error: 'The test email could not be sent.' }
        }
    } catch {
        result.value = { ok: false, error: 'The test email could not be sent.' }
    }
    testing.value = false
    if (result.value) {
        tested.value = { ok: result.value.ok, at: new Date().toISOString() }
    }
}
</script>

<template>
    <FormPage :title="editing ? account.name : 'New Email Account'"
        :description="editing ? `Last test: ${lastTest}` : 'SMTP details of the account that sends your email'"
        back="/settings/email-accounts" back-label="Email Accounts">
        <template #badge>
            <StatusBadge v-if="editing && account.is_default" tone="success">Default</StatusBadge>
            <StatusBadge v-if="editing && !account.is_active" tone="neutral">Disabled</StatusBadge>
            <StatusBadge v-if="!editing || form.isDirty" tone="warning">Not Saved</StatusBadge>
        </template>
        <template #actions>
            <button v-if="form.isDirty" type="button" class="btn" @click="form.reset()">Discard</button>
            <button v-if="!editing || form.isDirty" type="submit" form="email-account-form" class="btn btn-primary"
                :disabled="form.processing">Save</button>
        </template>

        <form id="email-account-form" @submit.prevent="save">
            <FormCard>
                <FormSection title="Account" icon="mail">
                    <FormGrid>
                        <Field label="Name" required hint="Shown in lists and logs" :error="form.errors.name"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.name" autocomplete="off" :invalid="invalid" />
                        </Field>
                        <Field label="From email" required :error="form.errors.from_email" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.from_email" type="email" autocomplete="off"
                                :invalid="invalid" />
                        </Field>
                        <Field label="From name" hint="Optional display name" :error="form.errors.from_name"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.from_name" autocomplete="off" :invalid="invalid" />
                        </Field>
                        <Field label="Host" required hint="SMTP server, e.g. smtp.gmail.com" :error="form.errors.host"
                            v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.host" autocomplete="off" :invalid="invalid" />
                        </Field>
                        <Field label="Port" required size="sm" :error="form.errors.port" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.port" type="number" min="1" max="65535" :invalid="invalid" />
                        </Field>
                        <Field label="Encryption" size="md" hint="TLS uses STARTTLS on the normal port"
                            :error="form.errors.encryption" v-slot="{ id }">
                            <Select :id="id" v-model="form.encryption" :options="encryptions" />
                        </Field>
                        <Field label="Username" hint="Leave blank if the server needs no login"
                            :error="form.errors.username" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.username" autocomplete="off" :invalid="invalid" />
                        </Field>
                        <Field label="Password" :required="!editing && !!form.username"
                            :hint="editing ? 'Leave blank to keep the current password' : (form.username ? 'Required when a username is set' : 'Not needed without a username')"
                            :error="form.errors.password" v-slot="{ id, invalid }">
                            <Input :id="id" v-model="form.password" type="password" autocomplete="new-password"
                                :invalid="invalid" />
                        </Field>
                    </FormGrid>
                </FormSection>

                <FormSection title="Status">
                    <div class="flex flex-col gap-2">
                        <Checkbox v-model="form.is_active" label="Active" />
                        <Checkbox v-model="form.is_default"
                            label="Default account (used when no account is picked)" />
                        <p class="text-[11px] text-muted">Only one account can be the default; choosing this one clears
                            the others.</p>
                    </div>
                </FormSection>

                <FormSection v-if="editing" title="Send test email" icon="send">
                    <FormGrid>
                        <Field label="To" hint="A test message is sent right away" v-slot="{ id }">
                            <Input :id="id" v-model="to" type="email" autocomplete="off" />
                        </Field>
                        <div class="flex items-end gap-2">
                            <button type="button" class="btn" :disabled="testing" @click="sendTest">
                                <Icon name="send" :size="13" />{{ testing ? 'Sending...' : 'Send test email' }}
                            </button>
                        </div>
                    </FormGrid>
                    <p v-if="result" role="status" class="mt-2 text-[12px]"
                        :class="result.ok ? 'text-green-700' : 'text-red-700'">
                        {{ result.ok ? 'Test email sent.' : result.error }}
                    </p>
                    <p class="mt-2 text-[11px] text-muted">Last test: {{ lastTest }}</p>
                </FormSection>
            </FormCard>
        </form>
    </FormPage>
</template>
