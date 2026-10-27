<script setup>
import { reactive } from 'vue'
import AppShell from '@core/layouts/AppShell.vue'
import FormPage from '@core/layouts/FormPage.vue'
import FormCard from '@core/layouts/FormCard.vue'
import FormSection from '@core/layouts/FormSection.vue'
import FormGrid from '@core/layouts/FormGrid.vue'
import Field from '@core/ui/Field.vue'
import Input from '@core/ui/Input.vue'
import Checkbox from '@core/ui/Checkbox.vue'

defineOptions({ layout: AppShell })

const form = reactive({ name: 'SaaS Starter', email: '', notifyExpiry: true, weekly: false })
</script>

<template>
    <FormPage title="General Settings" description="Settings use the same form layout and the same top action bar">
        <template #actions>
            <button type="button" class="btn">Cancel</button>
            <button type="button" class="btn btn-primary">Save</button>
        </template>

        <FormCard>
            <FormSection title="Workspace">
                <FormGrid>
                    <Field label="Workspace name" v-slot="{ id }"><Input :id="id" v-model="form.name" /></Field>
                    <Field label="Support email" v-slot="{ id }"><Input :id="id" v-model="form.email" type="email"
                            placeholder="support@example.com" /></Field>
                </FormGrid>
            </FormSection>

            <FormSection title="Notifications">
                <div class="flex flex-col gap-2">
                    <Checkbox v-model="form.notifyExpiry" label="Email me when a subscription expires" />
                    <Checkbox v-model="form.weekly" label="Weekly summary" />
                </div>
            </FormSection>
        </FormCard>
    </FormPage>
</template>