// Posts the login ahead of Inertia so the book can finish its unlock sequence
// before navigation happens. The server still does the whole authentication;
// this only reads the outcome and hands navigation back to Inertia.
//
// Anything unexpected returns { outcome: 'retry' } so the caller can fall back
// to a plain message — a failure here can never break sign-in.

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
    return match ? decodeURIComponent(match[1]) : null
}

function absoluteUrl(value) {
    try {
        return new URL(value, window.location.origin).href
    } catch (error) {
        return null
    }
}

function normalizeErrors(raw) {
    const out = {}
    Object.entries(raw ?? {}).forEach(([field, value]) => {
        const message = Array.isArray(value) ? value[0] : value
        if (typeof message === 'string' && message) out[field] = message
    })
    return out
}

function readJson(text) {
    try {
        return JSON.parse(text)
    } catch (error) {
        return null
    }
}

function retryAfterSeconds(response, data) {
    const header = Number(response.headers.get('Retry-After'))
    if (Number.isFinite(header) && header > 0) return header
    const body = Number(data?.retry_after ?? data?.retryAfter)
    if (Number.isFinite(body) && body > 0) return body
    return 60
}

export async function submitLogin({ url, version, payload }) {
    const token = xsrfToken()

    const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }
    if (token) headers['X-XSRF-TOKEN'] = token
    if (version) {
        headers['X-Inertia'] = 'true'
        headers['X-Inertia-Version'] = version
    }

    let response
    try {
        response = await fetch(url, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify(payload),
            redirect: 'follow',
        })
    } catch (error) {
        return { outcome: 'network' }
    }

    let text = ''
    try {
        text = await response.text()
    } catch (error) {
        return { outcome: 'network' }
    }

    const data = readJson(text)

    // Too many attempts.
    if (response.status === 429) {
        return {
            outcome: 'throttled',
            retryAfter: retryAfterSeconds(response, data),
            message: data?.message,
        }
    }

    // Rejected credentials.
    if (response.status === 422) {
        const errors = normalizeErrors(data?.errors ?? data?.props?.errors)
        return { outcome: 'invalid', errors, message: data?.message }
    }

    if (!response.ok) return { outcome: 'retry' }

    const isInertia = response.headers.get('X-Inertia') === 'true'
    if (isInertia && data) {
        // Still on the login component means the redirect never happened.
        if (!data.component || data.component === 'Auth/Login') {
            const errors = normalizeErrors(data.props?.errors)
            if (Object.keys(errors).length > 0) return { outcome: 'invalid', errors }
            return { outcome: 'retry' }
        }
        const target = absoluteUrl(data.url || response.url)
        return target ? { outcome: 'success', url: target } : { outcome: 'retry' }
    }

    // Redirect chain landed on a plain HTML response — use the final URL.
    const target = absoluteUrl(response.url)
    if (!target || new URL(target).pathname === '/login') return { outcome: 'retry' }
    return { outcome: 'success', url: target }
}
