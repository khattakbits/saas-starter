<script setup>
import { onMounted, useId } from 'vue'
import PageHeader from './PageHeader.vue'
import Icon from '@core/ui/Icon.vue'
import { useDocSide } from '@core/composables/useDocSide'

defineProps({ title: String, description: String, back: String, backLabel: String })

const { collapsed, toggle, restore } = useDocSide()
const panelId = useId()

// localStorage sirf mount ke baad (SSR aur hydration mismatch se bachne ke liye)
onMounted(restore)
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <PageHeader :title="title" :description="description" :back="back" :back-label="backLabel">
            <template #badge>
                <slot name="badge" />
            </template>
            <template #actions>
                <slot name="actions" />
            </template>
        </PageHeader>

        <div class="flex min-h-0 flex-1 gap-3">
            <aside v-if="$slots.side" :id="panelId"
                class="noscroll flex-none overflow-y-auto overflow-x-hidden transition-[width] duration-150 ease-out motion-reduce:transition-none"
                :class="collapsed ? 'w-10' : 'w-72'">
                <!-- Aik hi button: khula ho to "Hide panel", collapsed ho to rail ka expand button
                     (focus collapse ke baad bhi isi button par rehti hai) -->
                <div :class="collapsed ? 'flex justify-center' : ''">
                    <button type="button" class="btn" :class="collapsed ? 'btn-icon' : 'w-full justify-start'"
                        :aria-label="collapsed ? 'Show side panel' : undefined" :aria-expanded="!collapsed"
                        :aria-controls="panelId" @click="toggle">
                        <Icon name="panel" :size="14" />
                        <span v-if="!collapsed">Hide panel</span>
                    </button>
                </div>

                <!-- Panel content: collapsed hone par display:none, is liye tab order se bahar -->
                <div v-show="!collapsed" class="mt-3 w-72">
                    <slot name="side" />
                </div>
            </aside>

            <div class="noscroll min-h-0 min-w-0 flex-1 overflow-y-auto">
                <div class="flex flex-col gap-3 pb-1">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
