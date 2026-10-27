import { onBeforeUnmount, ref } from 'vue'

export function useFloating(anchorRef, { gap = 4, maxHeight = 260, width = null } = {}) {
    const style = ref({})
    let active = false

    function place(heightHint = maxHeight) {
        const el = anchorRef.value
        if (!el) return
        const r = el.getBoundingClientRect()
        const below = window.innerHeight - r.bottom - gap - 8
        const above = r.top - gap - 8
        const flip = below < Math.min(heightHint, 180) && above > below
        const maxH = Math.max(120, Math.min(maxHeight, flip ? above : below))
        const w = width ?? r.width
        const left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8))

        style.value = {
            position: 'fixed',
            left: left + 'px',
            width: w + 'px',
            maxHeight: maxH + 'px',
            ...(flip ? { bottom: window.innerHeight - r.top + gap + 'px' } : { top: r.bottom + gap + 'px' }),
        }
    }

    const onMove = () => place()
    function start() {
        if (active) return
        active = true
        window.addEventListener('resize', onMove)
        window.addEventListener('scroll', onMove, true)
    }
    function stop() {
        if (!active) return
        active = false
        window.removeEventListener('resize', onMove)
        window.removeEventListener('scroll', onMove, true)
    }
    onBeforeUnmount(stop)

    return { style, place, start, stop }
}