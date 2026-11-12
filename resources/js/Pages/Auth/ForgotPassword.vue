<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)

const form = useForm({ email: '' })
const submit = () => form.post('/forgot-password')
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-4">
        <form
            class="flex w-full max-w-[340px] flex-col gap-3 rounded-[10px] border border-line bg-surface p-5 shadow-sm"
            @submit.prevent="submit">
            <div class="flex items-center gap-1.5 text-[13px] font-semibold">
                <span class="size-4 rounded bg-accent"></span>Forgot password
            </div>

            <p v-if="flashSuccess" role="status"
                class="rounded-md bg-green-50 px-2 py-1.5 text-green-900">{{ flashSuccess }}</p>

            <p class="text-[11px] text-muted">Enter your email and we will send a reset link.</p>

            <Field label="Email" required :error="form.errors.email" v-slot="{ id, invalid }">
                <Input :id="id" v-model="form.email" type="email" autocomplete="username" autofocus
                    :invalid="invalid" />
            </Field>

            <button type="submit" class="btn btn-primary justify-center" :disabled="form.processing">
                {{ form.processing ? 'Sending...' : 'Send reset link' }}
            </button>

            <Link href="/login" class="text-center text-[11px] font-medium text-accent-dark hover:underline">Back to
                sign in</Link>
        </form>
    </div>
</template>
