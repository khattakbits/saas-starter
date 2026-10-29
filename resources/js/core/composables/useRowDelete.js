import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

/** const del = reactive(useRowDelete({ url: row => `/things/${row.id}`, onDone: () => list.refresh() })) */
export function useRowDelete({ url, onDone }) {
    const target = ref(null)
    const open = ref(false)
    const busy = ref(false)

    const ask = row => { target.value = row; open.value = true }
    const confirm = () => {
        busy.value = true
        router.delete(url(target.value), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onDone?.(),
            onFinish: () => { busy.value = false; open.value = false },
        })
    }

    return { target, open, busy, ask, confirm }
}