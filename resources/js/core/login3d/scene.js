// The book scene: renderer, lighting, state machine and the on-demand loop.
//
// Nothing renders while the page is at rest. A frame is requested only when
// something actually changes (pointer, caret, hands, key, lock, flip, resize),
// and the loop stops itself the moment every value has settled. The tab being
// hidden freezes everything.

import * as THREE from 'three'
import { FOV, CAMERA_DIST, coverMetrics, bx, by } from './metrics'
import { paintCoverArt } from './coverArt'
import { createMaterials, disposeObject } from './materials'
import { buildBook, buildKey } from './book'
import { buildFace } from './face'
import { createTweens, clamp, easeInOutCubic, easeOutCubic } from './ease'

const INSERT_WORLD = 1.6 // how far in front of the keyhole the key starts
const DEG = Math.PI / 180

export function createBookScene(host, options = {}) {
    // --- renderer -----------------------------------------------------------
    let renderer
    try {
        renderer = new THREE.WebGLRenderer({
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance',
            stencil: false,
        })
    } catch (error) {
        throw new Error('webgl-unavailable')
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5))
    renderer.setClearAlpha(0)
    renderer.shadowMap.enabled = false // hard rule: no shadow maps
    renderer.toneMapping = THREE.NoToneMapping

    const canvas = renderer.domElement
    canvas.style.display = 'block'
    canvas.style.width = '100%'
    canvas.style.height = '100%'
    host.appendChild(canvas)

    // --- cover texture, painted once ---------------------------------------
    const texture = new THREE.CanvasTexture(paintCoverArt())
    texture.colorSpace = THREE.SRGBColorSpace
    texture.anisotropy = Math.min(4, renderer.capabilities.getMaxAnisotropy())

    const materials = createMaterials(texture)

    const scene = new THREE.Scene()
    const camera = new THREE.PerspectiveCamera(FOV, 1, 0.1, 40)
    camera.position.set(0, 0, CAMERA_DIST)
    camera.lookAt(0, 0, 0)

    // --- lights: clean, brilliant studio lighting for pure white backdrop ---
    scene.add(new THREE.AmbientLight(0xffffff, 0.75))

    const keyLight = new THREE.DirectionalLight(0xfffaed, 1.35)
    keyLight.position.set(-3.2, 4.5, 5.5)
    scene.add(keyLight)

    const fill = new THREE.DirectionalLight(0xdce7f5, 0.65)
    fill.position.set(4.5, -1.5, 4.0)
    scene.add(fill)

    const rim = new THREE.DirectionalLight(0xffeed1, 0.6)
    rim.position.set(0, -3.0, -2.5)
    scene.add(rim)

    // --- book, face, key ----------------------------------------------------
    const book = buildBook(materials)
    scene.add(book.group)

    const face = buildFace(materials)
    book.coverFace.add(face.root)

    const key = buildKey(materials)
    key.rig.visible = false
    scene.add(key.rig)

    // --- runtime state ------------------------------------------------------
    let metrics = coverMetrics(host.clientWidth, host.clientHeight)
    let scale = metrics.w * metrics.worldPerPx
    let insertDist = INSERT_WORLD / scale

    book.group.scale.setScalar(scale)
    key.rig.scale.setScalar(scale)

    const tweens = createTweens()
    const look = new THREE.Vector2(0, -0.12)
    const smooth = new THREE.Vector2(0, -0.12)

    let phase = 'idle'
    let handT = 0
    let handDir = 0
    let glow = 0
    let destroyed = false
    let rafId = 0
    let lastTime = 0

    const keyholeWorld = new THREE.Vector3()
    const keyholeQuat = new THREE.Quaternion()

    const motionQuery =
        typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null
    let reduced = motionQuery ? motionQuery.matches : false

    const glowGold = new THREE.Color(0x2a1c06)
    const glowRed = new THREE.Color(0xff2a12)

    // --- helpers ------------------------------------------------------------
    function applyGaze() {
        const yaw = Math.asin(clamp(smooth.x, -1, 1) * 0.45)
        const pitch = -Math.asin(clamp(smooth.y, -1, 1) * 0.32)
        for (const eye of face.eyes) {
            eye.gaze.rotation.y = yaw
            eye.gaze.rotation.x = pitch
        }
    }

    function updateGaze(dt) {
        const k = 1 - Math.pow(1 - 0.18, Math.min(dt, 0.05) * 60)
        smooth.x += (look.x - smooth.x) * k
        smooth.y += (look.y - smooth.y) * k
        applyGaze()
        return Math.abs(look.x - smooth.x) > 0.0015 || Math.abs(look.y - smooth.y) > 0.0015
    }

    function applyHands() {
        const e = easeOutCubic(handT)
        for (const hand of face.hands) {
            hand.body.position.y = -face.handRise * (1 - e)
            hand.body.scale.setScalar(0.5 + 0.5 * e)
            hand.body.rotation.z = -0.5 * hand.side * (1 - e)
            hand.group.visible = handT > 0.002
        }
    }

    function updateHands(dt) {
        if (!handDir) return false
        const next = clamp(handT + handDir * (dt / 0.35), 0, 1)
        if (next === handT) {
            handDir = 0
            return false
        }
        handT = next
        if ((handT === 1 && handDir > 0) || (handT === 0 && handDir < 0)) handDir = 0
        applyHands()
        return true
    }

    function applyGlow(v) {
        glow = v
        const mat = materials.lockBody
        mat.emissive.copy(glowGold).lerp(glowRed, v)
        mat.emissiveIntensity = 1 + v * 2.2
    }

    function setFlip(deg) {
        book.coverPivot.rotation.y = -deg * DEG
        // Past 90° the printed side faces away: hide the eyes and hands so
        // nothing can poke through the inside of the cover.
        face.root.visible = deg < 90
        options.onFlip?.(deg)
    }

    function syncKeyhole() {
        book.keyholeAnchor.getWorldPosition(keyholeWorld)
        book.keyholeAnchor.getWorldQuaternion(keyholeQuat)
    }

    function fit() {
        const w = Math.max(1, host.clientWidth)
        const h = Math.max(1, host.clientHeight)
        metrics = coverMetrics(w, h)
        scale = metrics.w * metrics.worldPerPx
        insertDist = INSERT_WORLD / scale

        renderer.setSize(w, h, false)
        camera.aspect = w / h
        camera.updateProjectionMatrix()

        book.group.scale.setScalar(scale)
        key.rig.scale.setScalar(scale)
        syncKeyhole()
        wake()
    }

    // --- loop ---------------------------------------------------------------
    // rafId: 0 = idle, -1 = a frame is being computed, >0 = scheduled.
    function tick(now) {
        rafId = -1
        if (destroyed) return

        const dt = clamp((now - lastTime) / 1000, 0.001, 0.05)
        lastTime = now

        let active = tweens.advance(dt)
        if (updateGaze(dt)) active = true
        if (updateHands(dt)) active = true

        renderer.render(scene, camera)

        rafId = active ? requestAnimationFrame(tick) : 0
    }

    function wake() {
        if (destroyed || rafId) return
        if (typeof document !== 'undefined' && document.hidden) return
        lastTime = performance.now()
        rafId = requestAnimationFrame(tick)
    }

    // --- public actions -----------------------------------------------------
    function look_(x, y) {
        const nx = clamp(x, -1, 1)
        const ny = clamp(y, -1, 1)
        if (Math.abs(nx - look.x) < 1e-4 && Math.abs(ny - look.y) < 1e-4) return
        look.set(nx, ny)
        wake()
    }

    function setHands(on) {
        if (reduced) {
            handT = on ? 1 : 0
            handDir = 0
            applyHands()
            wake()
            return
        }
        const dir = on ? 1 : -1
        if (dir === handDir) return
        handDir = dir
        wake()
    }

    /** Flies the key in and slides it home; resolves when it is seated. */
    async function insertKey() {
        if (phase !== 'idle') return
        phase = 'inserting'

        syncKeyhole()
        key.rig.visible = true
        key.material.opacity = 1
        key.spin.rotation.z = 0
        key.slide.position.z = insertDist
        key.rig.quaternion.copy(keyholeQuat)

        if (reduced) {
            key.slide.position.z = 0
            phase = 'inserted'
            wake()
            return
        }

        const end = keyholeWorld.clone()
        const start = end.clone().add(new THREE.Vector3(0.34 * scale, -0.32 * scale, INSERT_WORLD))
        key.rig.position.copy(start)
        wake()

        await tweens.to(0.7, (p) => key.rig.position.lerpVectors(start, end, p), easeOutCubic)
        await tweens.to(0.35, (p) => {
            key.slide.position.z = insertDist * (1 - p)
        }, easeInOutCubic)

        phase = 'inserted'
    }

    /** Only ever called after the server accepted the credentials. */
    async function playSuccess() {
        if (phase === 'success' || phase === 'opened') return
        phase = 'success'

        if (reduced) {
            key.rig.visible = false
            book.shackle.position.y = 0.03
            book.shackle.rotation.z = 0.62
            setFlip(165)
            wake()
            return
        }

        // key turns -90°
        await tweens.to(0.35, (p) => {
            key.spin.rotation.z = -(Math.PI / 2) * p
        })

        // shackle lifts and swings open
        await tweens.to(0.45, (p) => {
            book.shackle.position.y = 0.03 * p
            book.shackle.rotation.z = 0.62 * p
        })

        // key pulls out and vanishes
        await tweens.to(0.25, (p) => {
            key.slide.position.z = insertDist * p
            key.material.opacity = 1 - p
        })
        key.rig.visible = false

        // cover swings open on the left hinge, the form turns with it
        await tweens.to(1.4, (p) => setFlip(165 * p), easeInOutCubic)
        await tweens.to(0.15, () => {})
    }

    /** Bad credentials, or the throttle: nothing unlocks. */
    async function playFailure() {
        phase = 'failure'
        if (reduced) {
            key.rig.visible = false
            phase = 'idle'
            return
        }

        // key twists 20° and back
        await tweens.to(0.3, (p) => {
            key.spin.rotation.z = 0.35 * Math.sin(p * Math.PI)
        })

        // lock shakes ±10° for 550ms with a red glow, key withdraws
        const shake = tweens.to(0.55, (p) => {
            const decay = 1 - p
            book.lock.rotation.z = Math.sin(p * Math.PI * 8) * 0.1745 * decay
            applyGlow(decay)
        })
        const withdraw = tweens.to(0.25, (p) => {
            key.slide.position.z = insertDist * p
            key.material.opacity = 1 - p
        })
        await Promise.all([shake, withdraw])

        key.rig.visible = false
        book.lock.rotation.z = 0
        applyGlow(0)
        phase = 'idle'
    }

    /** Empty fields: rattle the lock, no key, no request. */
    function shakeLock() {
        if (reduced) return
        wake()
        tweens.to(0.55, (p) => {
            const decay = 1 - p
            book.lock.rotation.z = Math.sin(p * Math.PI * 8) * 0.1745 * decay
            applyGlow(decay * 0.7)
        })
    }

    function reset() {
        phase = 'idle'
        handT = 0
        handDir = 0
        applyHands()
        key.rig.visible = false
        key.slide.position.z = insertDist
        key.spin.rotation.z = 0
        key.material.opacity = 1
        book.lock.rotation.z = 0
        book.shackle.rotation.z = 0
        book.shackle.position.y = 0
        applyGlow(0)
        setFlip(0)
        wake()
    }

    // --- listeners ----------------------------------------------------------
    function onVisibility() {
        if (document.hidden) {
            if (rafId > 0) cancelAnimationFrame(rafId)
            rafId = 0
        } else {
            wake()
        }
    }

    function onMotionChange(event) {
        reduced = event.matches
    }

    function onContextLost(event) {
        event.preventDefault()
        options.onUnsupported?.()
        destroy()
    }

    const observer = new ResizeObserver(() => fit())
    observer.observe(host)
    document.addEventListener('visibilitychange', onVisibility)
    canvas.addEventListener('webglcontextlost', onContextLost)
    motionQuery?.addEventListener('change', onMotionChange)

    // --- boot ---------------------------------------------------------------
    fit()
    applyGaze()
    applyHands()
    applyGlow(0)

    function destroy() {
        if (destroyed) return
        destroyed = true
        cancelAnimationFrame(rafId)
        rafId = 0
        tweens.clear()
        observer.disconnect()
        document.removeEventListener('visibilitychange', onVisibility)
        canvas.removeEventListener('webglcontextlost', onContextLost)
        motionQuery?.removeEventListener('change', onMotionChange)
        disposeObject(scene)
        texture.dispose()
        renderer.dispose()
        renderer.forceContextLoss()
        if (canvas.parentNode) canvas.parentNode.removeChild(canvas)
    }

    return {
        look: look_,
        setHands,
        insertKey,
        playSuccess,
        playFailure,
        shakeLock,
        reset,
        destroy,
        resize: fit,
    }
}
