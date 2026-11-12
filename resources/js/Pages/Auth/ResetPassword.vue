<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'

const props = defineProps({ token: String, email: String })

const form = useForm({
    token: props.token ?? '',
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
})

const submit = () => form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
})
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-4">
        <form
            class="flex w-full max-w-[340px] flex-col gap-3 rounded-[10px] border border-line bg-surface p-5 shadow-sm"
            @submit.prevent="submit">
            <div class="flex items-center gap-1.5 text-[13px] font-semibold">
                <span class="size-4 rounded bg-accent"></span>Choose a new password
            </div>

            <Field label="Email" required :error="form.errors.email" v-slot="{ id, invalid }">
                <Input :id="id" v-model="form.email" type="email" autocomplete="username" readonly :invalid="invalid" />
            </Field>

            <Field label="New password" required hint="At least 10 characters" :error="form.errors.password"
                v-slot="{ id, invalid }">
                <Input :id="id" v-model="form.password" type="password" autocomplete="new-password" autofocus
                    :invalid="invalid" />
            </Field>

            <Field label="Confirm password" required :error="form.errors.password_confirmation"
                v-slot="{ id, invalid }">
                <Input :id="id" v-model="form.password_confirmation" type="password" autocomplete="new-password"
                    :invalid="invalid" />
            </Field>

            <button type="submit" class="btn btn-primary justify-center" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : 'Set new password' }}
            </button>

            <Link href="/login" class="text-center text-[11px] font-medium text-accent-dark hover:underline">Back to
                sign in</Link>
        </form>
    </div>
</template>
