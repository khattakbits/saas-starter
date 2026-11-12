// Tiny tween runner — no animation library, no GSAP.
//
// `advance()` returns true while anything is still moving so the render loop
// can stop the moment the scene is at rest (the page must cost ~0 CPU idle).

export const clamp = (v, a, b) => Math.min(Math.max(v, a), b)
export const lerp = (a, b, t) => a + (b - a) * t
export const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3)
export const easeInCubic = (t) => t * t * t
export const easeInOutCubic = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2)

export function createTweens() {
    const list = []

    return {
        /** @returns {Promise<void>} resolves on the frame the tween completes */
        to(duration, onUpdate, ease = easeInOutCubic) {
            return new Promise((resolve) => {
                list.push({
                    t: 0,
                    duration: Math.max(0.001, duration),
                    onUpdate,
                    ease,
                    resolve,
                })
            })
        },

        /** @returns {boolean} true if any tween ran this frame */
        advance(dt) {
            if (!list.length) return false
            for (let i = list.length - 1; i >= 0; i -= 1) {
                const tw = list[i]
                tw.t += dt
                const p = Math.min(1, tw.t / tw.duration)
                tw.onUpdate(tw.ease(p), p)
                if (p >= 1) {
                    list.splice(i, 1)
                    tw.resolve()
                }
            }
            return true
        },

        get size() {
            return list.length
        },

        clear() {
            list.splice(0).forEach((tw) => tw.resolve())
        },
    }
}
