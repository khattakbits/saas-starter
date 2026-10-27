<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'

const flash = usePage().props.flash
const form = useForm({ email: '', password: '', remember: false })
const submit = () => form.post('/login', { onFinish: () => form.reset('password') })
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-4">
        <form class="flex w-full max-w-85 flex-col gap-3 rounded-[10px] border border-line bg-surface p-5 shadow-sm"
            @submit.prevent="submit">
            <div class="flex items-center gap-1.5 text-[13px] font-semibold">
                <span class="size-4 rounded bg-accent"></span>Sign in
            </div>
            <p v-if="flash?.error" role="alert" class="rounded-md bg-red-50 px-2 py-1.5 text-red-800">{{ flash.error }}
            </p>
            <Field label="Email" required :error="form.errors.email" v-slot="{ id, invalid }">
                <Input :id="id" v-model="form.email" type="email" autocomplete="username" autofocus
                    :invalid="invalid" />
            </Field>
            <Field label="Password" required :error="form.errors.password" v-slot="{ id, invalid }">
                <Input :id="id" v-model="form.password" type="password" autocomplete="current-password"
                    :invalid="invalid" />
            </Field>

            <label class="flex items-center gap-1.5 text-[11px] text-ink/80">
                <input v-model="form.remember" type="checkbox" class="size-3 accent-accent" /> Remember me
            </label>

            <button type="submit" class="btn btn-primary justify-center" :disabled="form.processing">
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>
    </div>
</template>