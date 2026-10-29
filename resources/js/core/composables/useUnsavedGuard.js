import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

export function useUnsavedGuard(isDirty, message = 'You have unsaved changes. Leave this page?') {
    let off
    const beforeUnload = e => {
        if (isDirty()) { e.preventDefault(); e.returnValue = '' }
    }

    onMounted(() => {
        window.addEventListener('beforeunload', beforeUnload)
        off = router.on('before', event => {
            if (!isDirty() || event.detail.visit.method !== 'get') return   // Save (put/post) rukna nahi chahiye
            if (!window.confirm(message)) {
                event.preventDefault()
                return false
            }
        })
    })

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnload)
        off?.()
    })
}