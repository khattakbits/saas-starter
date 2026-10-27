import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const matches = (path, prefix) =>
    prefix === '/' ? path === '/' : path === prefix || path.startsWith(prefix + '/')

export function useActiveModule() {
    const page = usePage()
    const modules = computed(() => page.props.menu ?? [])
    const path = computed(() => page.url.split('?')[0])

    // Active module URL se nikalta hai (refresh aur deep link par bhi sahi)
    const active = computed(() =>
        [...modules.value]
            .filter(m => matches(path.value, m.prefix))
            .sort((a, b) => b.prefix.length - a.prefix.length)[0]
    )

    // Sidebar mein sirf sab se lamba matching item highlight hota hai
    const activeHref = computed(() =>
        (active.value?.sections ?? [])
            .flatMap(s => s.items.map(i => i.href))
            .filter(h => matches(path.value, h))
            .sort((a, b) => b.length - a.length)[0]
    )

    const isItemActive = href => href === activeHref.value

    return { modules, active, isItemActive }
}