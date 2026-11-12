<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import BookScene from '@core/login3d/BookScene.vue'
import { coverMetrics } from '@core/login3d/metrics'
import { submitLogin } from '@core/login3d/submitLogin'

// Where the Admin Panel lives. This app mounts its panel at "/"; point this at
// "/dashboard" if you move it.
const ADMIN_ROUTE = '/'

const EMPTY_MESSAGE = 'Please enter both your email and password.'
const BAD_CREDENTIALS = 'These credentials do not match our records.'
const THROTTLED = 'Bohat zyada koshishein. Please wait a moment before trying again.'

const page = usePage()

const stage = ref(null)
const plane = ref(null)
const emailEl = ref(null)
const scene = ref(null)

const email = ref('')
const password = ref('')
const remember = ref(false)
const showPassword = ref(false)
const busy = ref(false)
const locked = ref(false)
const error = ref('')
const retryLeft = ref(0)

const supported = ref(true)
const sceneReady = ref(false)
const artHidden = ref(false)

const m = reactive({ w: 0, h: 0, left: 0, top: 0, perspective: 0, u: 0 })

const errorText = computed(() => (retryLeft.value > 0 ? `${error.value} (${retryLeft.value}s)` : error.value))

const stageStyle = computed(() => ({ perspective: `${m.perspective}px` }))

const boxStyle = computed(() => ({
    left: `${m.left}px`,
    top: `${m.top}px`,
    width: `${m.w}px`,
    height: `${m.h}px`,
    '--u': `${m.u}px`,
}))

// Realistic contact drop shadow beneath the book on pure white
const shadowStyle = computed(() => ({
    left: `${m.left + m.w * 0.05}px`,
    top: `${m.top + m.h - m.u * 0.4}px`,
    width: `${m.w * 0.9}px`,
    height: `${m.u * 3.6}px`,
}))

/* ------------------------------------------------------------------ layout */

let observer = null

function measure() {
    const el = stage.value
    if (!el) return
    const rect = el.getBoundingClientRect()
    const c = coverMetrics(rect.width, rect.height)
    m.w = c.w
    m.h = c.h
    m.left = c.left
    m.top = c.top
    m.perspective = c.perspective
    m.u = c.w / 40
}

/* -------------------------------------------------------------- eye targets */

let emailFocused = false
const pointer = { x: 0, y: 0 }

// Rule A — typing in Email: the pupils ride the caret.
function syncCaret() {
    const el = emailEl.value
    if (!el) return
    const index = el.selectionStart ?? el.value.length
    const x = Math.min(Math.max(index / 24, 0), 1) * 2 - 1
    scene.value?.look(x, -0.6)
}

// Rule B — Email not focused: the pupils follow the pointer / touch.
function onPointerMove(event) {
    pointer.x = (event.clientX / window.innerWidth) * 2 - 1
    pointer.y = (event.clientY / window.innerHeight) * 2 - 1
    if (!emailFocused) scene.value?.look(pointer.x, -pointer.y)
}

function onEmailFocus() {
    emailFocused = true
    syncCaret()
}

function onEmailBlur() {
    emailFocused = false
    scene.value?.look(pointer.x, -pointer.y)
}

/* --------------------------------------------------------------- password */

let passwordFocused = false

function onPasswordFocus() {
    passwordFocused = true
    if (!showPassword.value) scene.value?.setHands(true)
}

function onPasswordBlur() {
    passwordFocused = false
    scene.value?.setHands(false)
}

function toggleShow() {
    showPassword.value = !showPassword.value
    if (passwordFocused) scene.value?.setHands(!showPassword.value)
}

/* ------------------------------------------------------------- flip bridge */

function onFlip(deg) {
    const el = plane.value
    if (!el) return
    el.style.transform = deg ? `rotateY(${-deg}deg)` : ''
    el.style.pointerEvents = deg > 90 ? 'none' : ''
}

function onSceneReady() {
    sceneReady.value = true
    window.setTimeout(() => {
        artHidden.value = true
    }, 340)
    // re-apply the current look target once the scene exists
    scene.value?.look(pointer.x, -pointer.y)
}

function onSceneUnsupported() {
    supported.value = false
    sceneReady.value = false
    artHidden.value = false
}

/* ------------------------------------------------------------------ submit */

let retryTimer = 0

function startRetry(seconds) {
    const ms = Math.max(1, Math.ceil(seconds)) * 1000
    const until = Date.now() + ms
    retryLeft.value = Math.ceil(ms / 1000)
    locked.value = true
    busy.value = false

    window.clearInterval(retryTimer)
    retryTimer = window.setInterval(() => {
        const left = Math.ceil((until - Date.now()) / 1000)
        retryLeft.value = Math.max(0, left)
        if (left <= 0) {
            window.clearInterval(retryTimer)
            retryTimer = 0
            locked.value = false
        }
    }, 250)
}

function go(url) {
    const target = url || ADMIN_ROUTE
    try {
        const parsed = new URL(target, window.location.origin)
        if (parsed.origin === window.location.origin) {
            router.visit(parsed.pathname + parsed.search + parsed.hash)
            return
        }
        window.location.assign(parsed.href)
    } catch (err) {
        window.location.assign(ADMIN_ROUTE)
    }
}

async function submit() {
    if (busy.value || locked.value) return

    if (!email.value.trim() || !password.value) {
        error.value = EMPTY_MESSAGE
        scene.value?.shakeLock()
        return
    }

    error.value = ''
    busy.value = true
    locked.value = true
    emailFocused = false
    scene.value?.setHands(false)

    // The key starts moving straight away; it holds in the lock until the
    // server answers, so success can never play before the response.
    const inserting = scene.value?.insertKey() ?? Promise.resolve()

    const currentQuery = typeof window !== 'undefined' ? window.location.search : ''
    const result = await submitLogin({
        url: '/login' + currentQuery,
        version: page.version,
        payload: {
            email: email.value.trim(),
            password: password.value,
            remember: remember.value,
        },
    })

    if (result.outcome === 'success') {
        await inserting
        await scene.value?.playSuccess()
        go(result.url)
        return
    }

    if (result.outcome === 'invalid') {
        await inserting
        error.value =
            result.errors.email || result.errors.password || result.message || BAD_CREDENTIALS
        await scene.value?.playFailure()
        password.value = ''
        busy.value = false
        locked.value = false
        return
    }

    if (result.outcome === 'throttled') {
        await inserting
        error.value = THROTTLED
        await scene.value?.playFailure()
        startRetry(result.retryAfter)
        return
    }

    // network / unexpected
    await inserting
    error.value = result.message || 'Unable to connect to authentication server. Please try again.'
    await scene.value?.playFailure()
    password.value = ''
    busy.value = false
    locked.value = false
}

/* -------------------------------------------------------------- life cycle */

onMounted(() => {
    measure()
    observer = new ResizeObserver(measure)
    if (stage.value) observer.observe(stage.value)
    window.addEventListener('pointermove', onPointerMove, { passive: true })
    window.addEventListener('pointerdown', onPointerMove, { passive: true })
})

onBeforeUnmount(() => {
    observer?.disconnect()
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerdown', onPointerMove)
    window.clearInterval(retryTimer)
})
</script>

<template>
    <div ref="stage" class="stage" :style="stageStyle">
        <!-- 3D contact shadow on white floor -->
        <div class="surface-shadow" :style="shadowStyle" aria-hidden="true"></div>

        <div class="canvas-host">
            <BookScene v-if="supported" ref="scene" @ready="onSceneReady" @flip="onFlip"
                @unsupported="onSceneUnsupported" />
        </div>

        <!-- Royal Sapphire & 24K Gold Stand-in: shown until WebGL paints -->
        <div v-if="!artHidden" class="art" :class="{ 'art--gone': sceneReady }" :style="boxStyle" aria-hidden="true">
            <div class="art-f1"></div>
            <div class="art-f2"></div>
            <div class="art-f3"></div>
            <div class="art-corner art-corner-tl"></div>
            <div class="art-corner art-corner-tr"></div>
            <div class="art-corner art-corner-bl"></div>
            <div class="art-corner art-corner-br"></div>
            <div class="art-title">KhattakBits Login</div>
            <div class="art-sub">ENTERPRISE ACCESS • UNIFIED PORTAL</div>
            <div class="art-orn"><b></b></div>
            <div class="art-plate"></div>
            <div class="art-cap">SECURE ACCESS</div>
        </div>

        <div ref="plane" class="plane" :style="boxStyle">
            <form class="form" novalidate @submit.prevent="submit">
                <div class="fld fld-email">
                    <label class="sr" for="kb-email">Email</label>
                    <input id="kb-email" ref="emailEl" v-model="email" type="text" inputmode="email"
                        autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false"
                        placeholder="you@example.com" :disabled="locked" @focus="onEmailFocus" @blur="onEmailBlur"
                        @input="syncCaret" @keyup="syncCaret" @click="syncCaret" @select="syncCaret" />
                </div>

                <div class="fld fld-pass">
                    <label class="sr" for="kb-pass">Password</label>
                    <input id="kb-pass" v-model="password" :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password" placeholder="••••••••" :disabled="locked"
                        @focus="onPasswordFocus" @blur="onPasswordBlur" />
                    <button type="button" class="reveal" :aria-pressed="showPassword" @click="toggleShow">
                        {{ showPassword ? 'Hide' : 'Show' }}
                    </button>
                </div>

                <button class="submit" type="submit" :disabled="busy || locked">
                    {{ busy ? 'Unlocking...' : 'Open Ledger' }}
                </button>

                <!-- Auth Options: Remember me & Forgot Password -->
                <div class="fld-options">
                    <label class="remember-label" for="kb-remember">
                        <input id="kb-remember" v-model="remember" type="checkbox" :disabled="locked" class="remember-check" />
                        <span class="remember-box"></span>
                        <span class="remember-text">Remember</span>
                    </label>

                    <a href="/forgot-password" class="forgot-link">Forgot password?</a>
                </div>

                <p class="err" role="alert" aria-live="assertive">{{ errorText }}</p>
            </form>
        </div>
    </div>
</template>

<style scoped>
.stage {
    position: relative;
    width: 100%;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
    /* Pure white background with very subtle crisp studio luminance */
    background: radial-gradient(circle at 50% 50%, #ffffff 0%, #f9fafb 70%, #f3f4f6 100%);
    touch-action: manipulation;
}

/* --------------------------------------------------- contact floor shadow */

.surface-shadow {
    position: absolute;
    z-index: 0;
    border-radius: 50%;
    background: radial-gradient(ellipse at 50% 50%, rgba(15, 23, 42, 0.26) 0%, rgba(15, 23, 42, 0.12) 40%, transparent 70%);
    filter: blur(16px);
    pointer-events: none;
}

.canvas-host {
    position: absolute;
    inset: 0;
    z-index: 1;
}

/* ---------------------------------------------------------- fallback cover */

.art {
    position: absolute;
    z-index: 2;
    border-radius: 8px;
    overflow: hidden;
    background:
        radial-gradient(130% 90% at 20% 6%, rgba(150, 200, 255, 0.28), transparent 55%),
        radial-gradient(circle at 80% 90%, rgba(35, 80, 150, 0.2), transparent 45%),
        linear-gradient(145deg, #1f4e8c 0%, #15396b 48%, #0b1f3c 100%);
    box-shadow:
        inset 0 0 90px rgba(0, 0, 0, 0.65),
        0 35px 85px -20px rgba(10, 25, 50, 0.35),
        0 10px 30px rgba(0, 0, 0, 0.1);
    transition: opacity 0.35s ease;
}

.art--gone {
    opacity: 0;
}

.art-f1,
.art-f2,
.art-f3 {
    position: absolute;
    border: 2px solid rgba(254, 215, 104, 0.65);
    border-radius: 8px;
}

.art-f1 {
    inset: calc(var(--u) * 1.05);
}

.art-f2 {
    inset: calc(var(--u) * 1.75);
    border-width: 1px;
    opacity: 0.8;
}

.art-f3 {
    inset: calc(var(--u) * 2.65);
    border-width: 3.5px;
    border-color: rgba(254, 215, 104, 0.8);
}

.art-corner {
    position: absolute;
    width: calc(var(--u) * 3);
    height: calc(var(--u) * 3);
    border: 2px solid rgba(254, 215, 104, 0.75);
    pointer-events: none;
}

.art-corner-tl {
    top: calc(var(--u) * 2.9);
    left: calc(var(--u) * 2.9);
    border-right: 0;
    border-bottom: 0;
}

.art-corner-tr {
    top: calc(var(--u) * 2.9);
    right: calc(var(--u) * 2.9);
    border-left: 0;
    border-bottom: 0;
}

.art-corner-bl {
    bottom: calc(var(--u) * 2.9);
    left: calc(var(--u) * 2.9);
    border-right: 0;
    border-top: 0;
}

.art-corner-br {
    bottom: calc(var(--u) * 2.9);
    right: calc(var(--u) * 2.9);
    border-left: 0;
    border-top: 0;
}

.art-title {
    position: absolute;
    left: 0;
    right: 0;
    top: 14.7%;
    transform: translateY(-100%);
    text-align: center;
    font-family: "Cinzel", "Playfair Display", Georgia, serif;
    font-weight: 700;
    font-size: calc(var(--u) * 3.4);
    line-height: 1;
    color: #ffea9f;
    text-shadow: 0 3px 0 rgba(3, 8, 22, 0.88), 0 -1px 0 rgba(255, 255, 255, 0.4);
}

.art-sub {
    position: absolute;
    left: 0;
    right: 0;
    top: 18.4%;
    transform: translateY(-100%);
    text-align: center;
    font-size: calc(var(--u) * 0.94);
    letter-spacing: 0.24em;
    color: #ffdf70;
}

.art-orn {
    position: absolute;
    left: 28%;
    right: 28%;
    top: 20%;
    border-top: 1px solid rgba(254, 215, 104, 0.75);
}

.art-orn b {
    position: absolute;
    left: 50%;
    top: 0;
    width: 10px;
    height: 10px;
    background: #ffdf70;
    transform: translate(-50%, -50%) rotate(45deg);
    box-shadow: 0 0 6px rgba(254, 215, 104, 0.6);
}

.art-plate {
    position: absolute;
    left: 20.5%;
    right: 20.5%;
    top: 21.5%;
    height: 13.4%;
    border: 1.5px solid rgba(254, 215, 104, 0.75);
    border-radius: 14px;
    background: radial-gradient(65% 130% at 30% 50%, #0c1b36 0%, #020610 100%);
    box-shadow: inset 0 5px 24px rgba(0, 0, 0, 0.95);
}

.art-cap {
    position: absolute;
    left: 0;
    right: 0;
    top: 38.8%;
    transform: translateY(-100%);
    text-align: center;
    font-size: calc(var(--u) * 0.9);
    letter-spacing: 0.26em;
    color: #ffdf70;
}

/* ------------------------------------------------------------- form plane */

.plane {
    position: absolute;
    z-index: 3;
    transform-origin: 0 50%;
    backface-visibility: hidden;
    will-change: transform;
}

.form {
    position: absolute;
    inset: 0;
}

.sr {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
    border: 0;
}

/* Fields & button: left 8%, right 21% -> keeps ample breathing room away from the lock strap! */
.fld {
    position: absolute;
    left: 8%;
    right: 21%;
}

.fld-email {
    top: 42%;
    height: calc(var(--u) * 3.8);
}

.fld-pass {
    top: 53.5%;
    height: calc(var(--u) * 3.8);
}

.fld input {
    box-sizing: border-box;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0 calc(var(--u) * 0.75);
    border: 1.5px solid rgba(244, 188, 52, 0.85);
    border-radius: calc(var(--u) * 0.22);
    background: rgba(10, 24, 52, 0.88);
    box-shadow:
        inset 0 3px 12px rgba(0, 0, 0, 0.85),
        inset 0 -1px 0 rgba(255, 223, 112, 0.2),
        0 1px 0 rgba(255, 223, 112, 0.1);
    color: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: max(14px, calc(var(--u) * 0.95));
    letter-spacing: 0.03em;
    caret-color: #ffd866;
    outline: none;
    appearance: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.fld-pass input {
    padding-right: calc(var(--u) * 3.2);
}

.fld input::placeholder {
    color: rgba(180, 210, 255, 0.65);
    letter-spacing: 0.04em;
}

.fld input:focus-visible {
    border-color: #ffdf70;
    box-shadow:
        inset 0 3px 12px rgba(0, 0, 0, 0.85),
        0 0 0 2px rgba(255, 223, 112, 0.5),
        0 0 24px rgba(244, 188, 52, 0.35);
}

.fld input:disabled {
    opacity: 0.85;
    cursor: default;
}

.reveal {
    position: absolute;
    top: 50%;
    right: calc(var(--u) * 0.35);
    transform: translateY(-50%);
    padding: 3px 6px;
    border: 0;
    border-radius: 4px;
    background: none;
    color: #ffdf70;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: max(12px, calc(var(--u) * 0.62));
    letter-spacing: 0.14em;
    text-transform: uppercase;
    cursor: pointer;
    transition: color 0.15s ease;
}

.reveal:hover {
    color: #ffffff;
}

.reveal:focus-visible {
    outline: 2px solid #ffdf70;
    outline-offset: 1px;
}

.submit {
    position: absolute;
    top: 65.5%;
    left: 8%;
    right: 21%;
    height: calc(var(--u) * 4);
    padding: 0;
    border: 1px solid #c88c16;
    border-radius: calc(var(--u) * 0.22);
    background: linear-gradient(180deg, #fff7d4 0%, #ffde6b 35%, #f4bc34 70%, #c88c16 100%);
    color: #1a1002;
    font-family: Georgia, 'Times New Roman', serif;
    font-weight: 700;
    font-size: max(15px, calc(var(--u) * 0.98));
    letter-spacing: 0.18em;
    text-transform: uppercase;
    cursor: pointer;
    box-shadow:
        0 3px 0 #7a5408,
        inset 0 1px 0 rgba(255, 255, 255, 0.85),
        inset 0 -2px 8px rgba(120, 80, 10, 0.4);
    transition: transform 0.08s ease, box-shadow 0.08s ease;
}

.submit:active:not(:disabled) {
    transform: translateY(2px);
    box-shadow:
        0 1px 0 #7a5408,
        inset 0 2px 9px rgba(100, 65, 8, 0.55);
}

.submit:focus-visible {
    outline: 2px solid #ffdf70;
    outline-offset: 3px;
}

.submit:disabled {
    cursor: default;
    opacity: 0.8;
}

/* ---------------------------------------------------- auth options & links */

.fld-options {
    position: absolute;
    top: 73.8%;
    left: 8%;
    right: 21%;
    height: calc(var(--u) * 1.8);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.remember-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    user-select: none;
}

.remember-check {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.remember-box {
    width: calc(var(--u) * 0.9);
    height: calc(var(--u) * 0.9);
    min-width: 15px;
    min-height: 15px;
    border: 1.5px solid #f4bc34;
    border-radius: 3px;
    background: rgba(10, 24, 52, 0.95);
    box-shadow: inset 0 1px 4px rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.12s ease;
}

.remember-check:checked + .remember-box {
    background: #f4bc34;
    border-color: #ffdf70;
    box-shadow: 0 0 8px rgba(244, 188, 52, 0.6);
}

.remember-check:checked + .remember-box::after {
    content: '';
    width: 4px;
    height: 8px;
    border: solid #0b1a32;
    border-width: 0 2.2px 2.2px 0;
    transform: rotate(45deg) translate(-0.5px, -1px);
}

.remember-check:focus-visible + .remember-box {
    outline: 2px solid #ffdf70;
    outline-offset: 2px;
}

.remember-text {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: max(12px, calc(var(--u) * 0.62));
    color: #ffdf70;
    letter-spacing: 0.06em;
    text-shadow: 0 1px 3px rgba(3, 8, 22, 0.95);
}

.forgot-link {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: max(12px, calc(var(--u) * 0.62));
    color: #ffdf70;
    letter-spacing: 0.06em;
    text-shadow: 0 1px 3px rgba(3, 8, 22, 0.95);
    text-decoration: none;
    transition: color 0.15s ease, text-decoration 0.15s ease;
}

.forgot-link:hover {
    color: #ffffff;
    text-decoration: underline;
}

.forgot-link:focus-visible {
    outline: 2px solid #ffdf70;
    outline-offset: 2px;
}

.err {
    position: absolute;
    left: 6%;
    right: 23%;
    top: 77.8%;
    margin: 0;
    color: #ffb8a3;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: max(12px, calc(var(--u) * 0.6));
    line-height: 1.25;
    letter-spacing: 0.03em;
    text-align: center;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.95);
}

@media (prefers-reduced-motion: reduce) {
    .art,
    .submit {
        animation: none !important;
        transition: none !important;
    }
}
</style>
