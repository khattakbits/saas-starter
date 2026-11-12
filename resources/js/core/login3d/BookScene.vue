<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const emit = defineEmits(['ready', 'unsupported', 'flip'])

const host = ref(null)
let scene = null
let dead = false

onMounted(async () => {
    // Two frames: the styled HTML form paints first, then the 3D is brought in.
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))
    if (dead || !host.value) return

    try {
        // three.js lives in its own lazily-loaded chunk.
        const { createBookScene } = await import('./scene.js')
        if (dead || !host.value) return

        scene = createBookScene(host.value, {
            onFlip: (deg) => emit('flip', deg),
            onUnsupported: () => emit('unsupported'),
        })
        emit('ready')
    } catch (error) {
        emit('unsupported')
    }
})

onBeforeUnmount(() => {
    dead = true
    scene?.destroy()
    scene = null
})

defineExpose({
    look: (x, y) => scene?.look(x, y),
    setHands: (v) => scene?.setHands(v),
    insertKey: () => scene?.insertKey() ?? Promise.resolve(),
    playSuccess: () => scene?.playSuccess() ?? Promise.resolve(),
    playFailure: () => scene?.playFailure() ?? Promise.resolve(),
    shakeLock: () => scene?.shakeLock(),
    reset: () => scene?.reset(),
})
</script>

<template>
    <div ref="host" class="absolute inset-0" aria-hidden="true"></div>
</template>
